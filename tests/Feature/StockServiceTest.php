<?php

use App\Enums\StockMovementType;
use App\Exceptions\InsufficientStockException;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\StockService;

beforeEach(function () {
    $this->service = app(StockService::class);
    $this->user = User::factory()->admin()->create();
});

it('suma stock y registra el movimiento', function () {
    $product = Product::factory()->create(['stock' => 10]);

    $movement = $this->service->apply($product, StockMovementType::Entrada, 5, $this->user, 'Reposición');

    expect((float) $product->stock)->toBe(15.0)
        ->and((float) $movement->stock_before)->toBe(10.0)
        ->and((float) $movement->stock_after)->toBe(15.0)
        ->and((float) $movement->quantity)->toBe(5.0)
        ->and($movement->reason)->toBe('Reposición');
});

it('resta stock con cantidad negativa', function () {
    $product = Product::factory()->create(['stock' => 10]);

    $movement = $this->service->apply($product, StockMovementType::Salida, -4, $this->user, 'Vencido');

    expect((float) $product->fresh()->stock)->toBe(6.0)
        ->and((float) $movement->quantity)->toBe(-4.0);
});

it('permite dejar el stock exactamente en cero', function () {
    $product = Product::factory()->create(['stock' => 3]);

    $this->service->apply($product, StockMovementType::Salida, -3, $this->user, 'Todo vendido');

    expect((float) $product->fresh()->stock)->toBe(0.0);
});

it('nunca deja el stock en negativo y no registra nada si falla', function () {
    $product = Product::factory()->create(['stock' => 2]);

    expect(fn () => $this->service->apply($product, StockMovementType::Salida, -3, $this->user))
        ->toThrow(InsufficientStockException::class);

    expect((float) $product->fresh()->stock)->toBe(2.0)
        ->and(StockMovement::count())->toBe(0);
});

it('informa disponible y solicitado en la excepción', function () {
    $product = Product::factory()->create(['name' => 'Salteña', 'stock' => 2]);

    try {
        $this->service->apply($product, StockMovementType::Salida, -3, $this->user);
        $this->fail('Debió lanzar InsufficientStockException');
    } catch (InsufficientStockException $e) {
        expect($e->available)->toBe(2.0)
            ->and($e->requested)->toBe(3.0)
            ->and($e->getMessage())->toContain('Salteña');
    }
});

it('maneja decimales sin errores de redondeo', function () {
    $product = Product::factory()->byWeight()->create(['stock' => 0.3]);

    $this->service->apply($product, StockMovementType::Entrada, 0.1, $this->user);
    $this->service->apply($product, StockMovementType::Salida, -0.4, $this->user);

    expect((float) $product->fresh()->stock)->toBe(0.0);
});

it('rechaza movimientos de cantidad cero', function () {
    $product = Product::factory()->create();

    expect(fn () => $this->service->apply($product, StockMovementType::Entrada, 0, $this->user))
        ->toThrow(InvalidArgumentException::class);
});

it('corrige el inventario al valor contado', function () {
    $product = Product::factory()->create(['stock' => 10]);

    $movement = $this->service->correct($product, 7, $this->user, 'Conteo físico');

    expect((float) $product->stock)->toBe(7.0)
        ->and($movement->type)->toBe(StockMovementType::Correccion)
        ->and((float) $movement->quantity)->toBe(-3.0);
});

it('una corrección hacia arriba suma la diferencia', function () {
    $product = Product::factory()->create(['stock' => 10]);

    $movement = $this->service->correct($product, 12, $this->user, 'Conteo físico');

    expect((float) $movement->quantity)->toBe(2.0)
        ->and((float) $product->fresh()->stock)->toBe(12.0);
});

it('una corrección sin diferencia no registra movimiento', function () {
    $product = Product::factory()->create(['stock' => 10]);

    expect($this->service->correct($product, 10, $this->user, 'Igual'))->toBeNull()
        ->and(StockMovement::count())->toBe(0);
});

it('no acepta una corrección negativa', function () {
    $product = Product::factory()->create(['stock' => 10]);

    expect(fn () => $this->service->correct($product, -1, $this->user))
        ->toThrow(InvalidArgumentException::class);
});

it('trabaja con el stock real de la base, no con una copia vieja del modelo', function () {
    $product = Product::factory()->create(['stock' => 10]);
    $copiaVieja = Product::find($product->id);

    $this->service->apply($product, StockMovementType::Salida, -6, $this->user);

    // La copia vieja cree que hay 10, pero en la base quedan 4: no se puede sacar 5.
    expect(fn () => $this->service->apply($copiaVieja, StockMovementType::Salida, -5, $this->user))
        ->toThrow(InsufficientStockException::class);

    expect((float) $product->fresh()->stock)->toBe(4.0);
});

it('es atómico dentro de una transacción externa', function () {
    $product = Product::factory()->create(['stock' => 10]);

    try {
        DB::transaction(function () use ($product) {
            $this->service->apply($product, StockMovementType::Salida, -4, $this->user);
            throw new RuntimeException('falla posterior');
        });
    } catch (RuntimeException) {
    }

    expect((float) $product->fresh()->stock)->toBe(10.0)
        ->and(StockMovement::count())->toBe(0);
});
