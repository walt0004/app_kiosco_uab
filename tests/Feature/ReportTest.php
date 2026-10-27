<?php

use App\Enums\PaymentMethod;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Services\CashService;
use App\Services\ReportService;
use App\Services\SaleService;
use App\Support\PdfExporter;
use App\Support\ReportFilters;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->seller = User::factory()->seller()->create(['name' => 'Vendedora Ana']);
    $this->drinks = Category::factory()->create(['name' => 'Bebidas']);
    $this->snacks = Category::factory()->create(['name' => 'Snacks']);
    $this->cola = Product::factory()->create(['name' => 'Cola', 'category_id' => $this->drinks->id, 'sale_price' => 7, 'purchase_price' => 5, 'stock' => 50]);
    $this->chips = Product::factory()->create(['name' => 'Papas', 'category_id' => $this->snacks->id, 'sale_price' => 4, 'purchase_price' => 1, 'stock' => 50]);

    app(CashService::class)->open($this->seller, 0);
    $sales = app(SaleService::class);
    $this->s1 = $sales->create($this->seller, [['product_id' => $this->cola->id, 'quantity' => 2], ['product_id' => $this->chips->id, 'quantity' => 1]], PaymentMethod::Efectivo); // 18
    $this->s2 = $sales->create($this->seller, [['product_id' => $this->chips->id, 'quantity' => 3]], PaymentMethod::Qr); // 12
    $this->s3 = $sales->create($this->seller, [['product_id' => $this->cola->id, 'quantity' => 10]], PaymentMethod::Efectivo); // 70, se anula
    $sales->void($this->s3, $this->admin, 'Error de prueba');

    $this->filters = function (array $over = []) {
        $f = new ReportFilters(from: now()->startOfDay(), to: now()->endOfDay());
        foreach ($over as $key => $value) {
            $f->{$key} = $value;
        }

        return $f;
    };
});

it('los totales no incluyen ventas anuladas y calculan la utilidad', function () {
    $t = app(ReportService::class)->totals(($this->filters)());

    // 18 + 12 = 30 vendido; costo: 2*5 + 1*1 + 3*1 = 14
    expect($t['count'])->toBe(2)->and($t['total'])->toBe(30.0)->and($t['cost'])->toBe(14.0)->and($t['profit'])->toBe(16.0)->and($t['average'])->toBe(15.0);
});

it('respeta el filtro de método de pago', function () {
    $t = app(ReportService::class)->totals(($this->filters)(['method' => PaymentMethod::Qr]));

    expect($t['total'])->toBe(12.0);
});

it('el reporte por categoría agrupa los ingresos', function () {
    $report = app(ReportService::class)->build('categorias', ($this->filters)());
    $text = json_encode($report['rows'], JSON_UNESCAPED_UNICODE);

    expect($text)->toContain('Bebidas')->toContain('Snacks')->not->toContain('70');
});

it('el reporte de productos y el de vendedores funcionan', function () {
    $service = app(ReportService::class);

    expect(json_encode($service->build('productos', ($this->filters)())['rows']))->toContain('Cola');
    expect(json_encode($service->build('vendedores', ($this->filters)())['rows']))->toContain('Vendedora Ana');
});

it('el reporte de stock bajo lista solo lo que está bajo el mínimo', function () {
    Product::factory()->lowStock()->create(['name' => 'Chicle bajo']);

    $text = json_encode(app(ReportService::class)->build('stock-bajo', ($this->filters)())['rows'], JSON_UNESCAPED_UNICODE);

    expect($text)->toContain('Chicle bajo')->not->toContain('Cola');
});

it('el reporte de cierres muestra los turnos cerrados', function () {
    $session = app(CashService::class)->currentFor($this->seller);
    app(CashService::class)->close($session, $this->seller, 18, null);

    $report = app(ReportService::class)->build('cierres-caja', ($this->filters)());

    expect($report['rows'])->not->toBeEmpty();
});

it('lanza error con un reporte desconocido', function () {
    expect(fn () => app(ReportService::class)->build('inventado', ($this->filters)()))->toThrow(InvalidArgumentException::class);
});

describe('pantallas', function () {
    it('el administrador abre todos los reportes', function () {
        $this->actingAs($this->admin)->get(route('reports.index'))->assertOk();

        foreach (ReportService::SLUGS as $slug) {
            $this->get(route('reports.show', $slug))->assertOk();
            $this->get(route('reports.show', ['report' => $slug, 'from' => today()->toDateString(), 'to' => today()->toDateString()]))->assertOk();
        }
    });

    it('un reporte inexistente da 404', function () {
        $this->actingAs($this->admin)->get('/reportes/inventado')->assertNotFound();
    });

    it('valida los filtros', function () {
        $this->actingAs($this->admin);

        $this->get(route('reports.show', ['report' => 'ventas-periodo', 'from' => '2026-05-10', 'to' => '2026-05-01']))->assertSessionHasErrors('to');
        $this->get(route('reports.show', ['report' => 'ventas-periodo', 'from' => 'ayer']))->assertSessionHasErrors('from');
        $this->get(route('reports.show', ['report' => 'ventas-periodo', 'method' => 'cheque']))->assertSessionHasErrors('method');
        $this->get(route('reports.show', ['report' => 'ventas-periodo', 'group' => 'siglo']))->assertSessionHasErrors('group');
    });

    it('el vendedor no accede a los reportes', function () {
        $this->actingAs($this->seller);

        $this->get(route('reports.index'))->assertForbidden();
        $this->get(route('reports.show', 'productos'))->assertForbidden();
        $this->get(route('reports.pdf', 'productos'))->assertForbidden();
    });

    it('el PDF se descarga si DomPDF está instalado o avisa cómo instalarlo', function () {
        $response = $this->actingAs($this->admin)->get(route('reports.pdf', 'ventas-periodo'));

        if (PdfExporter::isAvailable()) {
            $response->assertOk()->assertHeader('content-type', 'application/pdf');
        } else {
            $response->assertRedirect()->assertSessionHas('error');
        }
    });

    it('el reporte por semana y por mes agrupa sin errores', function () {
        foreach (['dia', 'semana', 'mes'] as $group) {
            $this->actingAs($this->admin)->get(route('reports.show', ['report' => 'ventas-periodo', 'group' => $group]))->assertOk();
        }
    });
});

describe('panel', function () {
    it('el administrador ve totales y gráficos del día', function () {
        $this->actingAs($this->admin)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Ventas de hoy')
            ->assertSee('Bs 30,00');
    });

    it('el vendedor ve solo sus totales, sin utilidades', function () {
        $this->actingAs($this->seller)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Mis ventas de hoy')
            ->assertDontSee('Utilidad');
    });
});
