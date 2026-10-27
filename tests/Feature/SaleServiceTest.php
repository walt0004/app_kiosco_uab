<?php

use App\Enums\PaymentMethod;
use App\Enums\SaleStatus;
use App\Exceptions\BusinessRuleException;
use App\Exceptions\InsufficientStockException;
use App\Models\Product;
use App\Models\Sale;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\CashService;
use App\Services\SaleService;

beforeEach(function () {
    $this->sales = app(SaleService::class);
    $this->seller = User::factory()->seller()->create();
    $this->admin = User::factory()->admin()->create();
    $this->session = app(CashService::class)->open($this->seller, 0);
    $this->product = Product::factory()->create(['sale_price' => 6.50, 'purchase_price' => 4.00, 'stock' => 10]);
});

it('registra la venta, descuenta el stock y calcula el total', function () {
    $sale = $this->sales->create($this->seller, [['product_id' => $this->product->id, 'quantity' => 3]], PaymentMethod::Efectivo);

    expect((float) $sale->total)->toBe(19.5)
        ->and($sale->status)->toBe(SaleStatus::Completada)
        ->and($sale->items)->toHaveCount(1)
        ->and((float) $sale->items[0]->unit_price)->toBe(6.5)
        ->and((float) $sale->items[0]->purchase_price)->toBe(4.0)
        ->and((float) $this->product->fresh()->stock)->toBe(7.0)
        ->and($sale->number())->toStartWith('V-');

    $movement = StockMovement::where('sale_id', $sale->id)->first();
    expect((float) $movement->quantity)->toBe(-3.0)->and($movement->user_id)->toBe($this->seller->id);
});

it('guarda el nombre y precio de la venta aunque el producto cambie después', function () {
    $sale = $this->sales->create($this->seller, [['product_id' => $this->product->id, 'quantity' => 1]], PaymentMethod::Qr);
    $this->product->update(['name' => 'Otro nombre', 'sale_price' => 99]);

    $item = $sale->fresh()->items->first();
    expect($item->product_name)->not->toBe('Otro nombre')->and((float) $item->unit_price)->toBe(6.5);
});

it('junta líneas repetidas del mismo producto', function () {
    $sale = $this->sales->create($this->seller, [
        ['product_id' => $this->product->id, 'quantity' => 2],
        ['product_id' => $this->product->id, 'quantity' => 3],
    ], PaymentMethod::Efectivo);

    expect($sale->items)->toHaveCount(1)->and((float) $sale->items[0]->quantity)->toBe(5.0);
});

it('no permite vender sin stock suficiente y no deja nada a medias', function () {
    $other = Product::factory()->create(['stock' => 5, 'sale_price' => 1]);

    expect(fn () => $this->sales->create($this->seller, [
        ['product_id' => $other->id, 'quantity' => 2],
        ['product_id' => $this->product->id, 'quantity' => 11],
    ], PaymentMethod::Efectivo))->toThrow(InsufficientStockException::class);

    expect(Sale::count())->toBe(0)
        ->and((float) $other->fresh()->stock)->toBe(5.0)
        ->and((float) $this->product->fresh()->stock)->toBe(10.0);
});

it('no permite vender un producto agotado', function () {
    $out = Product::factory()->outOfStock()->create();

    expect(fn () => $this->sales->create($this->seller, [['product_id' => $out->id, 'quantity' => 1]], PaymentMethod::Efectivo))
        ->toThrow(InsufficientStockException::class);
});

it('no vende productos desactivados', function () {
    $inactive = Product::factory()->inactive()->create();

    expect(fn () => $this->sales->create($this->seller, [['product_id' => $inactive->id, 'quantity' => 1]], PaymentMethod::Efectivo))
        ->toThrow(BusinessRuleException::class, 'desactivado');
});

it('exige cantidades enteras en productos por unidad y acepta decimales por peso', function () {
    expect(fn () => $this->sales->create($this->seller, [['product_id' => $this->product->id, 'quantity' => 1.5]], PaymentMethod::Efectivo))
        ->toThrow(BusinessRuleException::class, 'número entero');

    $coffee = Product::factory()->byWeight()->create(['sale_price' => 110]);
    $sale = $this->sales->create($this->seller, [['product_id' => $coffee->id, 'quantity' => 0.25]], PaymentMethod::Efectivo);

    expect((float) $sale->total)->toBe(27.5)->and((float) $coffee->fresh()->stock)->toBe(10.25);
});

it('no registra ventas sin caja abierta', function () {
    $noCash = User::factory()->seller()->create();

    expect(fn () => $this->sales->create($noCash, [['product_id' => $this->product->id, 'quantity' => 1]], PaymentMethod::Efectivo))
        ->toThrow(BusinessRuleException::class, 'caja');
});

it('rechaza ventas vacías o con cantidades inválidas', function () {
    expect(fn () => $this->sales->create($this->seller, [], PaymentMethod::Efectivo))->toThrow(BusinessRuleException::class);
    expect(fn () => $this->sales->create($this->seller, [['product_id' => $this->product->id, 'quantity' => 0]], PaymentMethod::Efectivo))
        ->toThrow(BusinessRuleException::class);
    expect(fn () => $this->sales->create($this->seller, [['product_id' => $this->product->id, 'quantity' => -2]], PaymentMethod::Efectivo))
        ->toThrow(BusinessRuleException::class);
});

it('el mismo token no registra la venta dos veces', function () {
    $items = [['product_id' => $this->product->id, 'quantity' => 2]];

    $first = $this->sales->create($this->seller, $items, PaymentMethod::Efectivo, 'token-123');
    $second = $this->sales->create($this->seller, $items, PaymentMethod::Efectivo, 'token-123');

    expect($second->id)->toBe($first->id)
        ->and(Sale::count())->toBe(1)
        ->and((float) $this->product->fresh()->stock)->toBe(8.0);
});

describe('anulación', function () {
    beforeEach(function () {
        $this->sale = $this->sales->create($this->seller, [['product_id' => $this->product->id, 'quantity' => 4]], PaymentMethod::Efectivo);
    });

    it('devuelve el stock y registra quién, cuándo y por qué', function () {
        $voided = $this->sales->void($this->sale, $this->admin, 'Error al cobrar');

        expect($voided->status)->toBe(SaleStatus::Anulada)
            ->and($voided->voided_by)->toBe($this->admin->id)
            ->and($voided->void_reason)->toBe('Error al cobrar')
            ->and($voided->voided_at)->not->toBeNull()
            ->and((float) $this->product->fresh()->stock)->toBe(10.0);
    });

    it('una venta anulada no cuenta en el efectivo esperado', function () {
        $this->sales->void($this->sale, $this->admin, 'Error al cobrar');

        expect($this->session->fresh()->summary()['expected_cash'])->toBe(0.0);
    });

    it('un vendedor no puede anular', function () {
        expect(fn () => $this->sales->void($this->sale, $this->seller, 'Quiero anular'))->toThrow(BusinessRuleException::class);
        expect($this->sale->fresh()->status)->toBe(SaleStatus::Completada);
    });

    it('exige un motivo', function () {
        expect(fn () => $this->sales->void($this->sale, $this->admin, 'no'))->toThrow(BusinessRuleException::class, 'motivo');
    });

    it('no se anula dos veces (el stock no se devuelve doble)', function () {
        $this->sales->void($this->sale, $this->admin, 'Error al cobrar');

        expect(fn () => $this->sales->void($this->sale, $this->admin, 'Otra vez'))->toThrow(BusinessRuleException::class, 'ya fue anulada');
        expect((float) $this->product->fresh()->stock)->toBe(10.0);
    });

    it('no se anula si el turno ya fue cerrado', function () {
        app(CashService::class)->close($this->session, $this->seller, 26, 'Cierre');

        expect(fn () => $this->sales->void($this->sale, $this->admin, 'Muy tarde'))->toThrow(BusinessRuleException::class, 'cerrado');
        expect((float) $this->product->fresh()->stock)->toBe(6.0);
    });
});
