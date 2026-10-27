<?php

namespace App\Services;

use App\Enums\CashSessionStatus;
use App\Enums\ProductUnit;
use App\Enums\SaleStatus;
use App\Models\CashSession;
use App\Models\Product;
use App\Support\Format;
use App\Support\Money;
use App\Support\ReportFilters;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Consultas de los reportes. Cada reporte devuelve la misma estructura, que usan
 * tanto la pantalla como el PDF:
 *
 *   slug, title, subtitle, columns[], rows[], totals|null, cards[], chart|null, orientation
 *
 * Las ventas anuladas nunca se cuentan. Los cálculos de fechas se hacen en PHP
 * (no con funciones propias de MySQL) para que funcione igual en cualquier base de datos.
 */
class ReportService
{
    public const SLUGS = ['ventas-periodo', 'productos', 'categorias', 'vendedores', 'stock-bajo', 'cierres-caja'];

    /**
     * @return array<string, array{title: string, description: string, filters: list<string>}>
     */
    public static function definitions(): array
    {
        return [
            'ventas-periodo' => [
                'title' => 'Ventas y utilidades por período',
                'description' => 'Ventas, costo y utilidad por día, semana o mes, con gráfico.',
                'filters' => ['date', 'user', 'method', 'group'],
            ],
            'productos' => [
                'title' => 'Productos más vendidos',
                'description' => 'Qué productos se venden más y cuánto dejan de utilidad.',
                'filters' => ['date', 'category', 'user', 'method', 'order', 'top'],
            ],
            'categorias' => [
                'title' => 'Ventas por categoría',
                'description' => 'Ingresos y utilidad de cada categoría de producto.',
                'filters' => ['date', 'user', 'method'],
            ],
            'vendedores' => [
                'title' => 'Ventas por vendedor',
                'description' => 'Ventas, ticket promedio y anulaciones de cada usuario.',
                'filters' => ['date', 'method'],
            ],
            'stock-bajo' => [
                'title' => 'Productos con stock bajo',
                'description' => 'Productos agotados o en el mínimo que hay que reponer (estado actual).',
                'filters' => ['category'],
            ],
            'cierres-caja' => [
                'title' => 'Cierres de caja',
                'description' => 'Turnos de caja con efectivo esperado, contado y diferencias.',
                'filters' => ['date', 'user'],
            ],
        ];
    }

    public function build(string $slug, ReportFilters $f): array
    {
        $definition = self::definitions()[$slug] ?? throw new InvalidArgumentException("Reporte desconocido: {$slug}");

        $report = match ($slug) {
            'ventas-periodo' => $this->salesByPeriod($f),
            'productos' => $this->topProducts($f),
            'categorias' => $this->byCategory($f),
            'vendedores' => $this->bySeller($f),
            'stock-bajo' => $this->lowStock($f),
            'cierres-caja' => $this->cashClosures($f),
        };

        return array_merge([
            'slug' => $slug,
            'title' => $definition['title'],
            'subtitle' => $f->describe($definition['filters']),
            'totals' => null,
            'cards' => [],
            'chart' => null,
            'orientation' => 'portrait',
        ], $report);
    }

    // ---- Consultas reutilizables (también las usa el panel) -------------

    /** Ventas completadas dentro del rango y con los filtros indicados. */
    public function completedSales(ReportFilters $f): Builder
    {
        return DB::table('sales')
            ->where('sales.status', SaleStatus::Completada->value)
            ->whereBetween('sales.created_at', [$f->from, $f->to])
            ->when($f->userId, fn ($q, $id) => $q->where('sales.user_id', $id))
            ->when($f->method, fn ($q, $method) => $q->where('sales.payment_method', $method->value));
    }

    /**
     * Totales del rango: cantidad de ventas, total, costo y utilidad.
     *
     * @return array{count: int, total: float, cost: float, profit: float, average: float}
     */
    public function totals(ReportFilters $f): array
    {
        $sales = $this->completedSales($f)->selectRaw('COUNT(*) as n, COALESCE(SUM(sales.total), 0) as total')->first();

        $cost = (float) $this->completedSales($f)
            ->join('sale_items', 'sale_items.sale_id', '=', 'sales.id')
            ->sum(DB::raw('sale_items.quantity * sale_items.purchase_price'));

        $count = (int) $sales->n;
        $total = round((float) $sales->total, 2);
        $cost = round($cost, 2);

        return [
            'count' => $count,
            'total' => $total,
            'cost' => $cost,
            'profit' => round($total - $cost, 2),
            'average' => $count > 0 ? round($total / $count, 2) : 0.0,
        ];
    }

    /**
     * Total vendido por día (todos los días del rango, con 0 donde no hubo ventas).
     *
     * @return array<string, float> fecha Y-m-d => total
     */
    public function dailyTotals(ReportFilters $f): array
    {
        $rows = $this->completedSales($f)
            ->selectRaw('DATE(sales.created_at) as d, SUM(sales.total) as total')
            ->groupBy('d')
            ->pluck('total', 'd');

        $series = [];
        for ($day = $f->from->copy()->startOfDay(); $day <= $f->to; $day->addDay()) {
            $series[$day->toDateString()] = round((float) ($rows[$day->toDateString()] ?? 0), 2);
        }

        return $series;
    }

    /**
     * Total cobrado por método de pago.
     *
     * @return array<string, float> método => total
     */
    public function totalsByMethod(ReportFilters $f): array
    {
        return $this->completedSales($f)
            ->selectRaw('sales.payment_method as method, SUM(sales.total) as total')
            ->groupBy('sales.payment_method')
            ->pluck('total', 'method')
            ->map(fn ($v) => round((float) $v, 2))
            ->all();
    }

    /**
     * Productos más vendidos del rango, por ingresos.
     *
     * @return list<object{product: string, qty: string, revenue: string}>
     */
    public function topByRevenue(ReportFilters $f, int $limit = 5): array
    {
        return $this->itemsQuery($f)
            ->selectRaw('products.name as product, SUM(sale_items.quantity) as qty, SUM(sale_items.subtotal) as revenue')
            ->groupBy('sale_items.product_id', 'products.name')
            ->orderByRaw('revenue desc')
            ->orderBy('products.name')
            ->limit($limit)
            ->get()
            ->all();
    }

    // ---- Reportes -------------------------------------------------------

    private function salesByPeriod(ReportFilters $f): array
    {
        $sales = $this->completedSales($f)
            ->selectRaw('DATE(sales.created_at) as d, COUNT(*) as n, SUM(sales.total) as total')
            ->groupBy('d')
            ->orderBy('d')
            ->get();

        $costs = $this->completedSales($f)
            ->join('sale_items', 'sale_items.sale_id', '=', 'sales.id')
            ->selectRaw('DATE(sales.created_at) as d, SUM(sale_items.quantity * sale_items.purchase_price) as cost')
            ->groupBy('d')
            ->pluck('cost', 'd');

        // Se agrupa por día en la base de datos y se junta por semana o mes en PHP.
        $buckets = [];

        foreach ($sales as $row) {
            $date = Carbon::parse($row->d);

            if ($f->group === 'semana') {
                $start = $date->copy()->startOfWeek();
                $key = $start->toDateString();
                $label = 'Semana del '.$start->format('d/m/Y');
            } elseif ($f->group === 'mes') {
                $key = $date->format('Y-m');
                $label = Format::monthYear($date);
            } else {
                $key = $date->toDateString();
                $label = Format::dayWithWeekday($date);
            }

            $buckets[$key] ??= ['period' => $label, 'count' => 0, 'total' => 0.0, 'cost' => 0.0];
            $buckets[$key]['count'] += (int) $row->n;
            $buckets[$key]['total'] += (float) $row->total;
            $buckets[$key]['cost'] += (float) ($costs[$row->d] ?? 0);
        }

        ksort($buckets);

        $rows = [];
        foreach ($buckets as $b) {
            $total = round($b['total'], 2);
            $cost = round($b['cost'], 2);
            $rows[] = [
                'period' => $b['period'],
                'count' => $b['count'],
                'total' => $total,
                'average' => $b['count'] > 0 ? round($total / $b['count'], 2) : 0.0,
                'cost' => $cost,
                'profit' => round($total - $cost, 2),
            ];
        }

        $sum = $this->sumRows($rows, ['count', 'total', 'cost', 'profit']);
        $average = $sum['count'] > 0 ? round($sum['total'] / $sum['count'], 2) : 0.0;

        return [
            'columns' => [
                ['key' => 'period', 'label' => match ($f->group) {
                    'semana' => 'Semana',
                    'mes' => 'Mes',
                    default => 'Día',
                }, 'type' => 'text'],
                ['key' => 'count', 'label' => 'Ventas', 'type' => 'int'],
                ['key' => 'total', 'label' => 'Total vendido', 'type' => 'money'],
                ['key' => 'average', 'label' => 'Ticket promedio', 'type' => 'money'],
                ['key' => 'cost', 'label' => 'Costo', 'type' => 'money'],
                ['key' => 'profit', 'label' => 'Utilidad', 'type' => 'money'],
            ],
            'rows' => $rows,
            'totals' => ['period' => 'Total', 'count' => $sum['count'], 'total' => $sum['total'], 'average' => $average, 'cost' => $sum['cost'], 'profit' => $sum['profit']],
            'cards' => [
                ['label' => 'Ventas', 'value' => (string) $sum['count']],
                ['label' => 'Total vendido', 'value' => Format::money($sum['total'])],
                ['label' => 'Utilidad', 'value' => Format::money($sum['profit'])],
                ['label' => 'Ticket promedio', 'value' => Format::money($average)],
            ],
            'chart' => $rows === [] ? null : [
                'type' => 'bar',
                'labels' => array_column($rows, 'period'),
                'datasets' => [
                    ['label' => 'Total vendido', 'data' => array_column($rows, 'total')],
                    ['label' => 'Utilidad', 'data' => array_column($rows, 'profit')],
                ],
                'money' => true,
            ],
        ];
    }

    private function topProducts(ReportFilters $f): array
    {
        $query = $this->itemsQuery($f)
            ->when($f->categoryId, fn ($q, $id) => $q->where('products.category_id', $id))
            ->selectRaw('products.name as product, products.unit as unit, categories.name as category, SUM(sale_items.quantity) as qty, SUM(sale_items.subtotal) as revenue, SUM(sale_items.quantity * sale_items.purchase_price) as cost')
            ->groupBy('sale_items.product_id', 'products.name', 'products.unit', 'categories.name')
            ->orderByRaw(match ($f->order) {
                'ingresos' => 'revenue desc',
                'utilidad' => '(SUM(sale_items.subtotal) - SUM(sale_items.quantity * sale_items.purchase_price)) desc',
                default => 'qty desc',
            })
            ->orderBy('products.name');

        if ($f->top > 0) {
            $query->limit($f->top);
        }

        $rows = [];
        foreach ($query->get() as $r) {
            $revenue = round((float) $r->revenue, 2);
            $cost = round((float) $r->cost, 2);
            $rows[] = [
                'product' => $r->product,
                'category' => $r->category ?? 'Sin categoría',
                'qty' => round((float) $r->qty, 3),
                'unit' => ProductUnit::tryFrom($r->unit)?->short() ?? $r->unit,
                'revenue' => $revenue,
                'cost' => $cost,
                'profit' => round($revenue - $cost, 2),
            ];
        }

        $sum = $this->sumRows($rows, ['revenue', 'cost', 'profit']);

        return [
            'orientation' => 'landscape',
            'columns' => [
                ['key' => 'product', 'label' => 'Producto', 'type' => 'text'],
                ['key' => 'category', 'label' => 'Categoría', 'type' => 'text'],
                ['key' => 'qty', 'label' => 'Cantidad vendida', 'type' => 'qty'],
                ['key' => 'unit', 'label' => 'Unidad', 'type' => 'text'],
                ['key' => 'revenue', 'label' => 'Ingresos', 'type' => 'money'],
                ['key' => 'cost', 'label' => 'Costo', 'type' => 'money'],
                ['key' => 'profit', 'label' => 'Utilidad', 'type' => 'money'],
            ],
            'rows' => $rows,
            'totals' => $rows === [] ? null : ['product' => 'Total de los productos listados', 'revenue' => $sum['revenue'], 'cost' => $sum['cost'], 'profit' => $sum['profit']],
            'cards' => [
                ['label' => 'Productos listados', 'value' => (string) count($rows)],
                ['label' => 'Ingresos', 'value' => Format::money($sum['revenue'])],
                ['label' => 'Utilidad', 'value' => Format::money($sum['profit'])],
            ],
            'chart' => $rows === [] ? null : [
                'type' => 'bar',
                'horizontal' => true,
                'labels' => array_column(array_slice($rows, 0, 10), 'product'),
                'datasets' => [['label' => 'Ingresos', 'data' => array_column(array_slice($rows, 0, 10), 'revenue')]],
                'money' => true,
            ],
        ];
    }

    private function byCategory(ReportFilters $f): array
    {
        $data = $this->itemsQuery($f)
            ->selectRaw('categories.name as category, COUNT(DISTINCT sale_items.product_id) as products, SUM(sale_items.subtotal) as revenue, SUM(sale_items.quantity * sale_items.purchase_price) as cost')
            ->groupBy('products.category_id', 'categories.name')
            ->orderByRaw('revenue desc')
            ->get();

        $totalRevenue = round((float) $data->sum('revenue'), 2);

        $rows = [];
        foreach ($data as $r) {
            $revenue = round((float) $r->revenue, 2);
            $cost = round((float) $r->cost, 2);
            $rows[] = [
                'category' => $r->category ?? 'Sin categoría',
                'products' => (int) $r->products,
                'revenue' => $revenue,
                'cost' => $cost,
                'profit' => round($revenue - $cost, 2),
                'share' => $totalRevenue > 0 ? round($revenue / $totalRevenue * 100, 1) : 0.0,
            ];
        }

        $sum = $this->sumRows($rows, ['revenue', 'cost', 'profit']);

        return [
            'columns' => [
                ['key' => 'category', 'label' => 'Categoría', 'type' => 'text'],
                ['key' => 'products', 'label' => 'Productos vendidos', 'type' => 'int'],
                ['key' => 'revenue', 'label' => 'Ingresos', 'type' => 'money'],
                ['key' => 'cost', 'label' => 'Costo', 'type' => 'money'],
                ['key' => 'profit', 'label' => 'Utilidad', 'type' => 'money'],
                ['key' => 'share', 'label' => '% de los ingresos', 'type' => 'percent'],
            ],
            'rows' => $rows,
            'totals' => $rows === [] ? null : ['category' => 'Total', 'revenue' => $sum['revenue'], 'cost' => $sum['cost'], 'profit' => $sum['profit'], 'share' => 100.0],
            'cards' => [
                ['label' => 'Categorías con ventas', 'value' => (string) count($rows)],
                ['label' => 'Ingresos', 'value' => Format::money($sum['revenue'])],
                ['label' => 'Utilidad', 'value' => Format::money($sum['profit'])],
            ],
            'chart' => $rows === [] ? null : [
                'type' => 'doughnut',
                'labels' => array_column($rows, 'category'),
                'datasets' => [['label' => 'Ingresos', 'data' => array_column($rows, 'revenue')]],
                'money' => true,
            ],
        ];
    }

    private function bySeller(ReportFilters $f): array
    {
        // Este reporte compara usuarios, así que ignora el filtro de usuario.
        $f = new ReportFilters($f->from, $f->to, method: $f->method);

        $completed = $this->completedSales($f)
            ->join('users', 'users.id', '=', 'sales.user_id')
            ->selectRaw('sales.user_id as user_id, users.name as seller, COUNT(*) as n, SUM(sales.total) as total')
            ->groupBy('sales.user_id', 'users.name')
            ->orderByRaw('total desc')
            ->get();

        $voided = DB::table('sales')
            ->where('sales.status', SaleStatus::Anulada->value)
            ->whereBetween('sales.created_at', [$f->from, $f->to])
            ->when($f->method, fn ($q, $m) => $q->where('sales.payment_method', $m->value))
            ->selectRaw('sales.user_id as user_id, COUNT(*) as n, SUM(sales.total) as total')
            ->groupBy('sales.user_id')
            ->get()
            ->keyBy('user_id');

        $rows = [];
        foreach ($completed as $r) {
            $total = round((float) $r->total, 2);
            $rows[] = [
                'seller' => $r->seller,
                'count' => (int) $r->n,
                'total' => $total,
                'average' => $r->n > 0 ? round($total / $r->n, 2) : 0.0,
                'voided' => (int) ($voided[$r->user_id]->n ?? 0),
                'voided_total' => round((float) ($voided[$r->user_id]->total ?? 0), 2),
            ];
        }

        $sum = $this->sumRows($rows, ['count', 'total', 'voided', 'voided_total']);

        return [
            'columns' => [
                ['key' => 'seller', 'label' => 'Usuario', 'type' => 'text'],
                ['key' => 'count', 'label' => 'Ventas', 'type' => 'int'],
                ['key' => 'total', 'label' => 'Total vendido', 'type' => 'money'],
                ['key' => 'average', 'label' => 'Ticket promedio', 'type' => 'money'],
                ['key' => 'voided', 'label' => 'Anuladas', 'type' => 'int'],
                ['key' => 'voided_total', 'label' => 'Monto anulado', 'type' => 'money'],
            ],
            'rows' => $rows,
            'totals' => $rows === [] ? null : [
                'seller' => 'Total', 'count' => $sum['count'], 'total' => $sum['total'],
                'average' => $sum['count'] > 0 ? round($sum['total'] / $sum['count'], 2) : 0.0,
                'voided' => $sum['voided'], 'voided_total' => $sum['voided_total'],
            ],
            'cards' => [
                ['label' => 'Usuarios con ventas', 'value' => (string) count($rows)],
                ['label' => 'Total vendido', 'value' => Format::money($sum['total'])],
                ['label' => 'Ventas anuladas', 'value' => (string) $sum['voided']],
            ],
            'chart' => $rows === [] ? null : [
                'type' => 'bar',
                'labels' => array_column($rows, 'seller'),
                'datasets' => [['label' => 'Total vendido', 'data' => array_column($rows, 'total')]],
                'money' => true,
            ],
        ];
    }

    private function lowStock(ReportFilters $f): array
    {
        $products = Product::query()
            ->lowStock()
            ->leftJoin('categories', 'categories.id', '=', 'products.category_id')
            ->when($f->categoryId, fn ($q, $id) => $q->where('products.category_id', $id))
            ->select('products.*', 'categories.name as category_name')
            ->orderBy('products.stock')
            ->orderBy('products.name')
            ->get();

        $rows = [];
        $out = 0;
        foreach ($products as $p) {
            $isOut = $p->isOutOfStock();
            $out += $isOut ? 1 : 0;
            $rows[] = [
                'product' => $p->name,
                'category' => $p->category_name ?? 'Sin categoría',
                'unit' => $p->unit->short(),
                'stock' => (float) $p->stock,
                'min_stock' => (float) $p->min_stock,
                'missing' => max(0.0, round((float) $p->min_stock - (float) $p->stock, 3)),
                'status' => $isOut ? 'Agotado' : 'Stock bajo',
            ];
        }

        return [
            'columns' => [
                ['key' => 'product', 'label' => 'Producto', 'type' => 'text'],
                ['key' => 'category', 'label' => 'Categoría', 'type' => 'text'],
                ['key' => 'unit', 'label' => 'Unidad', 'type' => 'text'],
                ['key' => 'stock', 'label' => 'Stock actual', 'type' => 'qty'],
                ['key' => 'min_stock', 'label' => 'Stock mínimo', 'type' => 'qty'],
                ['key' => 'missing', 'label' => 'Falta para el mínimo', 'type' => 'qty'],
                ['key' => 'status', 'label' => 'Estado', 'type' => 'text'],
            ],
            'rows' => $rows,
            'cards' => [
                ['label' => 'Productos a reponer', 'value' => (string) count($rows)],
                ['label' => 'Agotados', 'value' => (string) $out],
                ['label' => 'Con stock bajo', 'value' => (string) (count($rows) - $out)],
            ],
        ];
    }

    private function cashClosures(ReportFilters $f): array
    {
        $sessions = CashSession::query()
            ->join('users', 'users.id', '=', 'cash_sessions.user_id')
            ->select('cash_sessions.*', 'users.name as user_name')
            ->whereBetween('cash_sessions.opened_at', [$f->from, $f->to])
            ->when($f->userId, fn ($q, $id) => $q->where('cash_sessions.user_id', $id))
            ->orderByDesc('cash_sessions.opened_at')
            ->get();

        $perSession = DB::table('sales')
            ->where('status', SaleStatus::Completada->value)
            ->whereIn('cash_session_id', $sessions->pluck('id'))
            ->selectRaw("cash_session_id, SUM(total) as total, SUM(CASE WHEN payment_method = 'efectivo' THEN total ELSE 0 END) as cash")
            ->groupBy('cash_session_id')
            ->get()
            ->keyBy('cash_session_id');

        $rows = [];
        foreach ($sessions as $s) {
            $sold = round((float) ($perSession[$s->id]->total ?? 0), 2);
            $cash = (float) ($perSession[$s->id]->cash ?? 0);
            $open = $s->status === CashSessionStatus::Abierta;

            $rows[] = [
                'id' => '#'.$s->id,
                'user' => $s->user_name,
                'opened_at' => $s->opened_at->format('d/m/Y H:i'),
                'closed_at' => $s->closed_at?->format('d/m/Y H:i') ?? 'Abierta',
                'opening' => (float) $s->opening_amount,
                'sold' => $sold,
                'expected' => $open ? round((float) $s->opening_amount + $cash, 2) : (float) $s->expected_cash,
                'counted' => $s->counted_cash !== null ? (float) $s->counted_cash : null,
                'difference' => $s->difference !== null ? (float) $s->difference : null,
            ];
        }

        $sum = $this->sumRows($rows, ['sold', 'difference']);
        $withDifference = count(array_filter($rows, fn ($r) => $r['difference'] !== null && $r['difference'] != 0.0));

        return [
            'orientation' => 'landscape',
            'columns' => [
                ['key' => 'id', 'label' => 'Turno', 'type' => 'text'],
                ['key' => 'user', 'label' => 'Usuario', 'type' => 'text'],
                ['key' => 'opened_at', 'label' => 'Apertura', 'type' => 'text'],
                ['key' => 'closed_at', 'label' => 'Cierre', 'type' => 'text'],
                ['key' => 'opening', 'label' => 'Inicial', 'type' => 'money'],
                ['key' => 'sold', 'label' => 'Vendido', 'type' => 'money'],
                ['key' => 'expected', 'label' => 'Efectivo esperado', 'type' => 'money'],
                ['key' => 'counted', 'label' => 'Efectivo contado', 'type' => 'money'],
                ['key' => 'difference', 'label' => 'Diferencia', 'type' => 'money'],
            ],
            'rows' => $rows,
            'totals' => $rows === [] ? null : ['id' => 'Total', 'sold' => $sum['sold'], 'difference' => $sum['difference']],
            'cards' => [
                ['label' => 'Turnos', 'value' => (string) count($rows)],
                ['label' => 'Total vendido', 'value' => Format::money($sum['sold'])],
                ['label' => 'Turnos con diferencia', 'value' => (string) $withDifference],
                ['label' => 'Diferencia acumulada', 'value' => Format::money($sum['difference'])],
            ],
        ];
    }

    // ---- Auxiliares -----------------------------------------------------

    /** Líneas de venta (completadas) con su producto y categoría. */
    private function itemsQuery(ReportFilters $f): Builder
    {
        return DB::table('sale_items')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->join('products', 'products.id', '=', 'sale_items.product_id')
            ->leftJoin('categories', 'categories.id', '=', 'products.category_id')
            ->where('sales.status', SaleStatus::Completada->value)
            ->whereBetween('sales.created_at', [$f->from, $f->to])
            ->when($f->userId, fn ($q, $id) => $q->where('sales.user_id', $id))
            ->when($f->method, fn ($q, $method) => $q->where('sales.payment_method', $method->value));
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  list<string>  $keys
     * @return array<string, float|int>
     */
    private function sumRows(array $rows, array $keys): array
    {
        $sum = array_fill_keys($keys, 0);

        foreach ($rows as $row) {
            foreach ($keys as $key) {
                $sum[$key] += (float) ($row[$key] ?? 0);
            }
        }

        foreach ($sum as $key => $value) {
            // Montos con 2 decimales; contadores como enteros.
            $sum[$key] = in_array($key, ['count', 'voided'], true) ? (int) $value : Money::fromCents(Money::toCents($value));
        }

        return $sum;
    }
}
