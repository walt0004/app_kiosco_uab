<?php

use App\Enums\StockMovementType;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->product = Product::factory()->create(['stock' => 10]);
    $this->actingAs($this->admin);
});

it('agrega stock', function () {
    $this->post(route('products.stock.store', $this->product), ['operation' => 'entrada', 'quantity' => '5'])
        ->assertRedirect(route('products.show', $this->product))
        ->assertSessionHas('success');

    expect((float) $this->product->fresh()->stock)->toBe(15.0);
    expect(StockMovement::sole()->type)->toBe(StockMovementType::Entrada);
});

it('retira stock con motivo', function () {
    $this->post(route('products.stock.store', $this->product), ['operation' => 'salida', 'quantity' => '4', 'reason' => 'Vencido'])
        ->assertSessionHasNoErrors();

    $movement = StockMovement::sole();
    expect((float) $this->product->fresh()->stock)->toBe(6.0)
        ->and($movement->type)->toBe(StockMovementType::Salida)
        ->and($movement->reason)->toBe('Vencido')
        ->and($movement->user_id)->toBe($this->admin->id);
});

it('exige motivo al retirar o corregir', function (string $operation) {
    $this->post(route('products.stock.store', $this->product), ['operation' => $operation, 'quantity' => '2'])
        ->assertSessionHasErrors('reason');

    expect((float) $this->product->fresh()->stock)->toBe(10.0);
})->with(['salida', 'correccion']);

it('no exige motivo al agregar stock', function () {
    $this->post(route('products.stock.store', $this->product), ['operation' => 'entrada', 'quantity' => '2'])
        ->assertSessionHasNoErrors();
});

it('no permite retirar más de lo que hay', function () {
    $this->post(route('products.stock.store', $this->product), ['operation' => 'salida', 'quantity' => '11', 'reason' => 'Error'])
        ->assertSessionHasErrors('quantity');

    expect((float) $this->product->fresh()->stock)->toBe(10.0)
        ->and(StockMovement::count())->toBe(0);
});

it('corrige el inventario al valor contado', function () {
    $this->post(route('products.stock.store', $this->product), ['operation' => 'correccion', 'quantity' => '8', 'reason' => 'Conteo'])
        ->assertSessionHasNoErrors();

    expect((float) $this->product->fresh()->stock)->toBe(8.0);
});

it('avisa cuando el conteo coincide y no registra nada', function () {
    $this->post(route('products.stock.store', $this->product), ['operation' => 'correccion', 'quantity' => '10', 'reason' => 'Conteo'])
        ->assertSessionHas('info');

    expect(StockMovement::count())->toBe(0);
});

it('permite corregir a cero', function () {
    $this->post(route('products.stock.store', $this->product), ['operation' => 'correccion', 'quantity' => '0', 'reason' => 'Se acabó'])
        ->assertSessionHasNoErrors();

    expect((float) $this->product->fresh()->stock)->toBe(0.0);
});

it('rechaza cantidades inválidas', function (array $payload) {
    $this->post(route('products.stock.store', $this->product), $payload + ['operation' => 'entrada'])
        ->assertSessionHasErrors('quantity');
})->with([
    'vacía' => [['quantity' => '']],
    'negativa' => [['quantity' => '-2']],
    'cero al agregar' => [['quantity' => '0']],
    'texto' => [['quantity' => 'abc']],
    'decimal en producto por unidad' => [['quantity' => '1.5']],
    'demasiados decimales' => [['quantity' => '1.12345']],
]);

it('rechaza operaciones desconocidas', function () {
    $this->post(route('products.stock.store', $this->product), ['operation' => 'borrar', 'quantity' => '1'])
        ->assertSessionHasErrors('operation');
});

it('admite cantidades decimales en productos por peso', function () {
    $cafe = Product::factory()->byWeight()->create(['stock' => 2.5]);

    $this->post(route('products.stock.store', $cafe), ['operation' => 'salida', 'quantity' => '0.250', 'reason' => 'Muestra'])
        ->assertSessionHasNoErrors();

    expect((float) $cafe->fresh()->stock)->toBe(2.25);
});
