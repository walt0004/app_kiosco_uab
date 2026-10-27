<?php

use App\Enums\PaymentMethod;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use App\Services\CashService;
use App\Services\SaleService;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->seller = User::factory()->seller()->create();
    $this->admin = User::factory()->admin()->create();
    $this->product = Product::factory()->create(['name' => 'Salteña', 'sale_price' => 6.00, 'stock' => 10]);
});

function payload(Product $p, float $qty = 2, array $extra = []): array
{
    return array_merge([
        'items' => [['product_id' => $p->id, 'quantity' => $qty]],
        'payment_method' => 'efectivo',
        'token' => (string) Str::uuid(),
    ], $extra);
}

it('redirige a la caja si no hay turno abierto', function () {
    $this->actingAs($this->seller)->get(route('sales.create'))->assertRedirect(route('cash.index'));
});

it('muestra la pantalla de venta con la caja abierta', function () {
    app(CashService::class)->open($this->seller, 0);

    $this->actingAs($this->seller)->get(route('sales.create'))->assertOk()->assertSee('Registrar venta');
});

it('busca productos por nombre y solo devuelve activos', function () {
    Product::factory()->inactive()->create(['name' => 'Salteña vieja']);

    $this->actingAs($this->seller)->getJson(route('sales.search', ['q' => 'Salte']))
        ->assertOk()
        ->assertJsonCount(1)
        ->assertJsonPath('0.name', 'Salteña')
        ->assertJsonPath('0.price', 6);
});

it('registra una venta por JSON y devuelve el comprobante', function () {
    app(CashService::class)->open($this->seller, 0);

    $response = $this->actingAs($this->seller)->postJson(route('sales.store'), payload($this->product))
        ->assertCreated();

    $sale = Sale::firstOrFail();
    $response->assertJsonPath('redirect', route('sales.show', $sale));
    expect((float) $sale->total)->toBe(12.0)->and((float) $this->product->fresh()->stock)->toBe(8.0);

    $this->get(route('sales.show', $sale))->assertOk()->assertSee('Salteña')->assertSee($sale->number());
});

it('ignora los precios que envíe el navegador', function () {
    app(CashService::class)->open($this->seller, 0);

    $data = payload($this->product);
    $data['items'][0]['unit_price'] = 0.01;
    $data['total'] = 0.01;

    $this->actingAs($this->seller)->postJson(route('sales.store'), $data)->assertCreated();

    expect((float) Sale::first()->total)->toBe(12.0);
});

it('responde 422 con mensaje si no hay stock', function () {
    app(CashService::class)->open($this->seller, 0);

    $this->actingAs($this->seller)->postJson(route('sales.store'), payload($this->product, 11))
        ->assertStatus(422)
        ->assertJsonStructure(['message']);

    expect(Sale::count())->toBe(0);
});

it('responde 422 sin caja abierta', function () {
    $this->actingAs($this->seller)->postJson(route('sales.store'), payload($this->product))
        ->assertStatus(422);
});

it('valida los datos de la venta', function () {
    app(CashService::class)->open($this->seller, 0);
    $this->actingAs($this->seller);

    $this->postJson(route('sales.store'), [])->assertStatus(422)->assertJsonValidationErrors(['items', 'payment_method']);
    $this->postJson(route('sales.store'), payload($this->product, 0))->assertStatus(422)->assertJsonValidationErrors('items.0.quantity');
    $this->postJson(route('sales.store'), payload($this->product, 1, ['payment_method' => 'bitcoin']))->assertStatus(422)->assertJsonValidationErrors('payment_method');
    $this->postJson(route('sales.store'), ['items' => [['product_id' => 9999, 'quantity' => 1]], 'payment_method' => 'qr'])
        ->assertStatus(422)->assertJsonValidationErrors('items.0.product_id');
});

it('un doble envío con el mismo token no duplica la venta', function () {
    app(CashService::class)->open($this->seller, 0);
    $data = payload($this->product);

    $this->actingAs($this->seller)->postJson(route('sales.store'), $data)->assertCreated();
    $this->postJson(route('sales.store'), $data)->assertCreated();

    expect(Sale::count())->toBe(1)->and((float) $this->product->fresh()->stock)->toBe(8.0);
});

it('el vendedor solo ve sus propias ventas', function () {
    $other = User::factory()->seller()->create(['name' => 'Otro Vendedor']);
    app(CashService::class)->open($this->seller, 0);
    app(CashService::class)->open($other, 0);
    app(SaleService::class)->create($this->seller, [['product_id' => $this->product->id, 'quantity' => 1]], PaymentMethod::Efectivo);
    $foreign = app(SaleService::class)->create($other, [['product_id' => $this->product->id, 'quantity' => 1]], PaymentMethod::Qr);

    $json = dataTable($this->actingAs($this->seller), 'sales.data');
    expect($json->json('recordsTotal'))->toBe(1)->and(dataTableText($json))->not->toContain('Otro Vendedor');

    $this->get(route('sales.show', $foreign))->assertForbidden();

    expect(dataTable($this->actingAs($this->admin), 'sales.data')->json('recordsTotal'))->toBe(2);
    $this->get(route('sales.show', $foreign))->assertOk();
});

it('filtra las ventas por método y valida fechas', function () {
    app(CashService::class)->open($this->seller, 0);
    app(SaleService::class)->create($this->seller, [['product_id' => $this->product->id, 'quantity' => 1]], PaymentMethod::Efectivo);
    app(SaleService::class)->create($this->seller, [['product_id' => $this->product->id, 'quantity' => 1]], PaymentMethod::Qr);

    $this->actingAs($this->admin);
    expect(dataTable($this, 'sales.data', ['method' => 'qr'])->json('recordsFiltered'))->toBe(1);
    dataTable($this, 'sales.data', ['from' => '2026-02-10', 'to' => '2026-02-01'])->assertStatus(422);
    dataTable($this, 'sales.data', ['method' => 'cheque'])->assertStatus(422);
});

describe('anular desde la web', function () {
    beforeEach(function () {
        app(CashService::class)->open($this->seller, 0);
        $this->sale = app(SaleService::class)->create($this->seller, [['product_id' => $this->product->id, 'quantity' => 4]], PaymentMethod::Efectivo);
    });

    it('el administrador anula y se devuelve el stock', function () {
        $this->actingAs($this->admin)->post(route('sales.void', $this->sale), ['reason' => 'Se equivocó el cobro'])
            ->assertRedirect(route('sales.show', $this->sale))
            ->assertSessionHas('success');

        expect($this->sale->fresh()->isVoided())->toBeTrue()->and((float) $this->product->fresh()->stock)->toBe(10.0);
        $this->get(route('sales.show', $this->sale))->assertSee('Se equivocó el cobro');
    });

    it('el vendedor no puede anular', function () {
        $this->actingAs($this->seller)->post(route('sales.void', $this->sale), ['reason' => 'Se equivocó el cobro'])->assertForbidden();
        expect($this->sale->fresh()->isVoided())->toBeFalse();
    });

    it('exige el motivo', function () {
        $this->actingAs($this->admin)->post(route('sales.void', $this->sale), ['reason' => ''])->assertSessionHasErrors('reason');
        $this->post(route('sales.void', $this->sale), ['reason' => 'x'])->assertSessionHasErrors('reason');
        expect($this->sale->fresh()->isVoided())->toBeFalse();
    });

    it('el botón de anular solo aparece para el administrador', function () {
        $this->actingAs($this->seller)->get(route('sales.show', $this->sale))->assertOk()->assertDontSee('Anular venta');
        $this->actingAs($this->admin)->get(route('sales.show', $this->sale))->assertOk()->assertSee('Anular venta');
    });
});

it('exige iniciar sesión', function () {
    $this->get(route('sales.index'))->assertRedirect(route('login'));
    $this->postJson(route('sales.store'), [])->assertUnauthorized();
});
