<?php

namespace App\Http\Controllers;

use App\Enums\ProductUnit;
use App\Enums\StockMovementType;
use App\Http\Requests\SaveProductRequest;
use App\Models\Category;
use App\Models\Product;
use App\Services\StockService;
use App\Support\DataTable;
use App\Support\Format;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function __construct(private readonly StockService $stock) {}

    /**
     * Columnas de la tabla. El vendedor no ve el precio de compra, así que la
     * posición de cada columna depende del rol; la vista y el servidor usan esta misma lista.
     *
     * @return list<array{data: string, title: string, order: ?string, className?: string}>
     */
    private function columns(bool $isAdmin): array
    {
        return array_values(array_filter([
            ['data' => 'name', 'title' => 'Producto', 'order' => 'products.name'],
            ['data' => 'category', 'title' => 'Categoría', 'order' => 'categories.name'],
            ['data' => 'unit', 'title' => 'Unidad', 'order' => 'products.unit'],
            $isAdmin ? ['data' => 'purchase_price', 'title' => 'P. compra', 'order' => 'products.purchase_price', 'className' => 'dt-right'] : null,
            ['data' => 'sale_price', 'title' => 'P. venta', 'order' => 'products.sale_price', 'className' => 'dt-right'],
            ['data' => 'stock', 'title' => 'Stock', 'order' => 'products.stock', 'className' => 'dt-right'],
            ['data' => 'status', 'title' => 'Estado', 'order' => 'products.is_active'],
            ['data' => 'actions', 'title' => '', 'order' => null, 'className' => 'dt-right'],
        ]));
    }

    public function index(Request $request): View
    {
        $isAdmin = $request->user()->isAdmin();

        return view('products.index', [
            'columns' => array_map(
                fn ($c) => ['data' => $c['data'], 'title' => $c['title'], 'orderable' => $c['order'] !== null] + (isset($c['className']) ? ['className' => $c['className']] : []),
                $this->columns($isAdmin),
            ),
            'categories' => Category::orderBy('name')->get(['id', 'name']),
            'filters' => [
                'estado' => $request->query('estado'),
                'stock' => $request->query('stock'),
                'categoria' => $request->query('categoria'),
            ],
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $isAdmin = $request->user()->isAdmin();
        $estado = $request->input('estado');
        $stock = $request->input('stock');
        $categoria = $request->input('categoria');

        $query = Product::query()
            ->leftJoin('categories', 'categories.id', '=', 'products.category_id')
            ->select('products.*', 'categories.name as category_name')
            ->when($estado === 'activos', fn ($q) => $q->where('products.is_active', true))
            ->when($estado === 'inactivos', fn ($q) => $q->where('products.is_active', false))
            ->when($stock === 'bajo', fn ($q) => $q->where('products.stock', '>', 0)->whereColumn('products.stock', '<=', 'products.min_stock'))
            ->when($stock === 'agotado', fn ($q) => $q->where('products.stock', '<=', 0))
            ->when($categoria === 'ninguna', fn ($q) => $q->whereNull('products.category_id'))
            ->when(is_numeric($categoria), fn ($q) => $q->where('products.category_id', (int) $categoria));

        $orderable = [];
        foreach ($this->columns($isAdmin) as $index => $column) {
            if ($column['order'] !== null) {
                $orderable[$index] = $column['order'];
            }
        }

        return DataTable::of($query, $request)
            ->searchable(['products.name', 'products.code', 'categories.name'])
            ->orderable($orderable)
            ->defaultOrder('products.name')
            ->response(function (Product $product) use ($isAdmin) {
                $row = [
                    'name' => view('products._name', ['product' => $product])->render(),
                    'category' => $product->category_name ? e($product->category_name) : '<span class="text-slate-400">Sin categoría</span>',
                    'unit' => e($product->unit->label()),
                    'sale_price' => Format::money($product->sale_price),
                    'stock' => view('products._stock', ['product' => $product])->render(),
                    'status' => view('components.status-badge', ['active' => $product->is_active])->render(),
                    'actions' => view('products._actions', ['product' => $product, 'isAdmin' => $isAdmin])->render(),
                ];

                if ($isAdmin) {
                    $row['purchase_price'] = Format::money($product->purchase_price);
                }

                return $row;
            });
    }

    public function create(): View
    {
        return view('products.create', [
            'product' => new Product(['unit' => ProductUnit::Unidad, 'is_active' => true]),
            'units' => ProductUnit::cases(),
            'categories' => Category::active()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(SaveProductRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $initialStock = (float) $data['stock'];

        $product = DB::transaction(function () use ($data, $initialStock, $request) {
            $product = Product::create(collect($data)->except('stock')->all());

            if ($initialStock > 0) {
                $this->stock->apply($product, StockMovementType::Inicial, $initialStock, $request->user(), 'Inventario inicial');
            }

            return $product;
        });

        return redirect()
            ->route('products.show', $product)
            ->with('success', "Producto «{$product->name}» creado correctamente.");
    }

    public function show(Product $product): View
    {
        $product->load('category');

        return view('products.show', [
            'product' => $product,
            'movements' => $product->stockMovements()
                ->with('user:id,name')
                ->latest('created_at')
                ->latest('id')
                ->paginate(10),
        ]);
    }

    public function edit(Product $product): View
    {
        return view('products.edit', [
            'product' => $product,
            'units' => ProductUnit::cases(),
            // Incluye la categoría actual aunque se haya desactivado.
            'categories' => Category::where(fn ($q) => $q->where('is_active', true)->orWhere('id', $product->category_id))
                ->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(SaveProductRequest $request, Product $product): RedirectResponse
    {
        $product->update($request->validated());

        return redirect()
            ->route('products.show', $product)
            ->with('success', 'Producto actualizado correctamente.');
    }

    /** Activa o desactiva el producto (no se borran: el historial de ventas depende de ellos). */
    public function toggle(Product $product): RedirectResponse
    {
        $product->update(['is_active' => ! $product->is_active]);

        return back()->with('success', $product->is_active
            ? "«{$product->name}» ahora está activo."
            : "«{$product->name}» fue desactivado y ya no aparecerá en ventas.");
    }
}
