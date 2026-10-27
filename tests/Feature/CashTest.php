<?php

use App\Enums\CashSessionStatus;
use App\Enums\PaymentMethod;
use App\Exceptions\BusinessRuleException;
use App\Models\CashSession;
use App\Models\Product;
use App\Models\User;
use App\Services\CashService;
use App\Services\SaleService;

beforeEach(function () {
    $this->cash = app(CashService::class);
    $this->seller = User::factory()->seller()->create();
    $this->admin = User::factory()->admin()->create();
});

it('abre una caja con el monto inicial', function () {
    $session = $this->cash->open($this->seller, 50);

    expect($session->status)->toBe(CashSessionStatus::Abierta)
        ->and((float) $session->opening_amount)->toBe(50.0)
        ->and($this->cash->currentFor($this->seller)->is($session))->toBeTrue();
});

it('no permite dos cajas abiertas para el mismo usuario', function () {
    $this->cash->open($this->seller, 10);

    expect(fn () => $this->cash->open($this->seller, 10))->toThrow(BusinessRuleException::class);
    expect(CashSession::where('user_id', $this->seller->id)->count())->toBe(1);
});

it('cada usuario puede tener su propia caja a la vez', function () {
    $this->cash->open($this->seller, 10);
    $this->cash->open($this->admin, 20);

    expect(CashSession::open()->count())->toBe(2);
});

it('rechaza un monto inicial negativo', function () {
    expect(fn () => $this->cash->open($this->seller, -1))->toThrow(BusinessRuleException::class);
});

it('cierra la caja cuando el efectivo cuadra', function () {
    $session = $this->cash->open($this->seller, 100);
    $product = Product::factory()->create(['sale_price' => 5.00, 'stock' => 10]);
    app(SaleService::class)->create($this->seller, [['product_id' => $product->id, 'quantity' => 2]], PaymentMethod::Efectivo);
    app(SaleService::class)->create($this->seller, [['product_id' => $product->id, 'quantity' => 1]], PaymentMethod::Qr);

    // Esperado = inicial + ventas en efectivo (el QR no entra en la caja).
    $closed = $this->cash->close($session, $this->seller, 110);

    expect($closed->status)->toBe(CashSessionStatus::Cerrada)
        ->and((float) $closed->expected_cash)->toBe(110.0)
        ->and((float) $closed->difference)->toBe(0.0)
        ->and($closed->open_for_user_id)->toBeNull();

    // Al cerrar, puede abrir otra.
    expect($this->cash->open($this->seller, 0))->toBeInstanceOf(CashSession::class);
});

it('exige notas cuando hay faltante o sobrante', function () {
    $session = $this->cash->open($this->seller, 100);

    expect(fn () => $this->cash->close($session, $this->seller, 90))->toThrow(BusinessRuleException::class, 'faltante');
    expect(fn () => $this->cash->close($session, $this->seller, 120, '   '))->toThrow(BusinessRuleException::class, 'sobrante');

    $closed = $this->cash->close($session, $this->seller, 90, 'Se dio vuelto de más');

    expect((float) $closed->difference)->toBe(-10.0)->and($closed->notes)->toBe('Se dio vuelto de más');
});

it('no cierra dos veces el mismo turno', function () {
    $session = $this->cash->open($this->seller, 0);
    $this->cash->close($session, $this->seller, 0);

    expect(fn () => $this->cash->close($session, $this->seller, 0))->toThrow(BusinessRuleException::class);
});

describe('pantallas de caja', function () {
    it('abre y cierra la caja desde la web', function () {
        $this->actingAs($this->seller)->post(route('cash.open'), ['opening_amount' => '25.50'])
            ->assertRedirect(route('cash.index'));

        $session = CashSession::firstOrFail();
        expect((float) $session->opening_amount)->toBe(25.5);

        $this->post(route('cash.close', $session), ['counted_cash' => '25.50'])
            ->assertRedirect(route('cash.show', $session));

        expect($session->fresh()->status)->toBe(CashSessionStatus::Cerrada);
    });

    it('valida el monto inicial', function () {
        $this->actingAs($this->seller)->post(route('cash.open'), ['opening_amount' => 'abc'])
            ->assertSessionHasErrors('opening_amount');
        $this->post(route('cash.open'), ['opening_amount' => '-5'])->assertSessionHasErrors('opening_amount');
        $this->post(route('cash.open'), ['opening_amount' => '1.234'])->assertSessionHasErrors('opening_amount');
    });

    it('muestra un error si hay diferencia sin notas', function () {
        $session = $this->cash->open($this->seller, 10);

        $this->actingAs($this->seller)->post(route('cash.close', $session), ['counted_cash' => '5'])
            ->assertSessionHas('error');

        expect($session->fresh()->isOpen())->toBeTrue();
    });

    it('un vendedor no ve ni cierra la caja de otro', function () {
        $other = User::factory()->seller()->create();
        $session = $this->cash->open($other, 10);

        $this->actingAs($this->seller)->get(route('cash.show', $session))->assertForbidden();
        $this->post(route('cash.close', $session), ['counted_cash' => '10'])->assertForbidden();
        expect($session->fresh()->isOpen())->toBeTrue();
    });

    it('el administrador puede ver cualquier caja', function () {
        $session = $this->cash->open($this->seller, 10);

        $this->actingAs($this->admin)->get(route('cash.show', $session))->assertOk();
    });

    it('el historial solo muestra las cajas propias al vendedor', function () {
        $other = User::factory()->seller()->create(['name' => 'Otro Vendedor']);
        $this->cash->open($other, 10);
        $this->cash->open($this->seller, 10);

        $this->actingAs($this->seller)->get(route('cash.history'))->assertOk();
        $json = dataTable($this->actingAs($this->seller), 'cash.data');
        expect($json->json('recordsTotal'))->toBe(1)
            ->and(dataTableText($json))->not->toContain('Otro Vendedor');

        expect(dataTable($this->actingAs($this->admin), 'cash.data')->json('recordsTotal'))->toBe(2);
    });
});
