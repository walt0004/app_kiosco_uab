<?php

use App\Models\Product;
use App\Models\User;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->seller = User::factory()->seller()->create();
    $this->product = Product::factory()->create(['name' => 'Salteña', 'purchase_price' => 4.00, 'sale_price' => 6.00]);
});

it('el vendedor puede consultar el catálogo', function () {
    $this->actingAs($this->seller)->get(route('products.index'))->assertOk();
    expect(dataTableText(dataTable($this->actingAs($this->seller), 'products.data')))->toContain('Salteña');
    $this->actingAs($this->seller)->get(route('products.show', $this->product))->assertOk();
});

it('el vendedor no ve precios de compra ni ganancias', function () {
    $this->actingAs($this->seller)->get(route('products.index'))->assertDontSee('P. compra');

    $json = dataTable($this->actingAs($this->seller), 'products.data');
    expect(dataTableText($json))->not->toContain('Bs 4,00')->toContain('Bs 6,00');
    expect(array_keys($json->json('data.0')))->not->toContain('purchase_price');

    $this->actingAs($this->seller)->get(route('products.show', $this->product))
        ->assertDontSee('Precio de compra')
        ->assertDontSee('Ganancia por');
});

it('el administrador sí ve precios de compra', function () {
    $this->actingAs($this->admin)->get(route('products.index'))->assertSee('P. compra');
    expect(dataTableText(dataTable($this->actingAs($this->admin), 'products.data')))->toContain('Bs 4,00');
    $this->actingAs($this->admin)->get(route('products.show', $this->product))->assertSee('Precio de compra');
});

it('el vendedor no puede crear, editar ni ajustar productos', function () {
    $this->actingAs($this->seller);

    $this->get(route('products.create'))->assertForbidden();
    $this->post(route('products.store'), [])->assertForbidden();
    $this->get(route('products.edit', $this->product))->assertForbidden();
    $this->put(route('products.update', $this->product), [])->assertForbidden();
    $this->patch(route('products.toggle', $this->product))->assertForbidden();
    $this->get(route('products.stock.create', $this->product))->assertForbidden();
    $this->post(route('products.stock.store', $this->product), [])->assertForbidden();
});

it('el administrador accede a la gestión de productos', function () {
    $this->actingAs($this->admin);

    $this->get(route('products.create'))->assertOk();
    $this->get(route('products.edit', $this->product))->assertOk();
    $this->get(route('products.stock.create', $this->product))->assertOk();
});

it('solo el administrador ve los botones de gestión', function () {
    $this->actingAs($this->seller)->get(route('products.index'))->assertDontSee('Nuevo producto');
    expect(dataTableText(dataTable($this->actingAs($this->seller), 'products.data')))->not->toContain('Ajustar stock');

    $this->actingAs($this->admin)->get(route('products.index'))->assertSee('Nuevo producto');
    expect(dataTableText(dataTable($this->actingAs($this->admin), 'products.data')))->toContain('Ajustar stock');
});

it('muestra el panel a ambos roles', function () {
    $this->actingAs($this->admin)->get(route('dashboard'))->assertOk();
    $this->actingAs($this->seller)->get(route('dashboard'))->assertOk();
});
