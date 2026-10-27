<?php

use App\Enums\ProductUnit;
use App\Enums\StockMovementType;
use App\Models\Category;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->actingAs($this->admin);
});

function validProduct(array $overrides = []): array
{
    return array_merge([
        'name' => 'Empanada de queso',
        'code' => 'EMP-001',
        'category_id' => Category::firstOrCreate(['name' => 'Comida preparada'])->id,
        'unit' => 'unidad',
        'purchase_price' => '2.50',
        'sale_price' => '4.00',
        'stock' => '40',
        'min_stock' => '10',
        'is_active' => '1',
    ], $overrides);
}

it('crea un producto y registra el stock inicial en el historial', function () {
    $response = $this->post(route('products.store'), validProduct());

    $product = Product::firstWhere('name', 'Empanada de queso');
    $response->assertRedirect(route('products.show', $product));

    expect((float) $product->stock)->toBe(40.0)
        ->and($product->unit)->toBe(ProductUnit::Unidad)
        ->and($product->category->name)->toBe('Comida preparada')
        ->and($product->is_active)->toBeTrue();

    $movement = $product->stockMovements()->sole();
    expect($movement->type)->toBe(StockMovementType::Inicial)
        ->and((float) $movement->quantity)->toBe(40.0)
        ->and((float) $movement->stock_before)->toBe(0.0)
        ->and((float) $movement->stock_after)->toBe(40.0)
        ->and($movement->user_id)->toBe($this->admin->id);
});

it('con stock inicial 0 no crea movimiento', function () {
    $this->post(route('products.store'), validProduct(['stock' => '0']))->assertRedirect();

    expect(StockMovement::count())->toBe(0);
});

it('permite crear sin código', function () {
    $this->post(route('products.store'), validProduct(['code' => '']))->assertSessionHasNoErrors();

    expect(Product::first()->code)->toBeNull();
});

it('valida los campos obligatorios', function () {
    $this->post(route('products.store'), [])
        ->assertSessionHasErrors(['name', 'category_id', 'unit', 'purchase_price', 'sale_price', 'stock', 'min_stock']);

    expect(Product::count())->toBe(0);
});

it('rechaza nombre o código duplicados', function () {
    Product::factory()->create(['name' => 'Empanada de queso', 'code' => 'EMP-001']);

    $this->post(route('products.store'), validProduct())
        ->assertSessionHasErrors(['name' => 'Ya existe un producto con ese nombre.', 'code' => 'Ya existe un producto con ese código.']);
});

it('rechaza precios y stocks inválidos', function (array $override, string $field) {
    $this->post(route('products.store'), validProduct($override))->assertSessionHasErrors($field);
})->with([
    'precio de venta cero' => [['sale_price' => '0'], 'sale_price'],
    'precio de venta negativo' => [['sale_price' => '-3'], 'sale_price'],
    'precio de compra negativo' => [['purchase_price' => '-1'], 'purchase_price'],
    'precio con 3 decimales' => [['sale_price' => '4.555'], 'sale_price'],
    'precio no numérico' => [['sale_price' => 'abc'], 'sale_price'],
    'stock negativo' => [['stock' => '-5'], 'stock'],
    'stock mínimo negativo' => [['min_stock' => '-1'], 'min_stock'],
    'unidad inexistente' => [['unit' => 'metro'], 'unit'],
    'código con caracteres raros' => [['code' => 'AB C<>'], 'code'],
    'categoría inexistente' => [['category_id' => 99999], 'category_id'],
    'categoría vacía' => [['category_id' => ''], 'category_id'],
]);

it('no permite asignar una categoría desactivada a un producto nuevo', function () {
    $inactive = Category::factory()->inactive()->create();

    $this->post(route('products.store'), validProduct(['category_id' => $inactive->id]))
        ->assertSessionHasErrors('category_id');
});

it('un producto puede conservar su categoría aunque se haya desactivado', function () {
    $category = Category::factory()->create();
    $product = Product::factory()->create(['category_id' => $category->id, 'name' => 'Salteña', 'code' => null]);
    $category->update(['is_active' => false]);

    $this->put(route('products.update', $product), validProduct(['name' => 'Salteña', 'code' => '', 'category_id' => $category->id]))
        ->assertSessionHasNoErrors();
});

it('solo admite cantidades enteras en unidades que no son de peso', function () {
    $this->post(route('products.store'), validProduct(['stock' => '2.5']))->assertSessionHasErrors('stock');
    $this->post(route('products.store'), validProduct(['min_stock' => '1.5']))->assertSessionHasErrors('min_stock');
});

it('admite decimales en productos por peso', function () {
    $this->post(route('products.store'), validProduct(['unit' => 'kg', 'stock' => '2.5', 'min_stock' => '0.5']))
        ->assertSessionHasNoErrors();

    expect((float) Product::first()->stock)->toBe(2.5);
});

it('no permite fijar el stock mediante la edición', function () {
    $product = Product::factory()->create(['stock' => 20]);

    $this->put(route('products.update', $product), validProduct(['name' => $product->name, 'code' => '', 'category_id' => $product->category_id, 'stock' => '999']))
        ->assertRedirect(route('products.show', $product));

    expect((float) $product->fresh()->stock)->toBe(20.0)
        ->and(StockMovement::count())->toBe(0);
});

it('edita los datos del producto', function () {
    $product = Product::factory()->create(['name' => 'Viejo', 'sale_price' => 5]);
    $newCategory = Category::factory()->create();

    $this->put(route('products.update', $product), validProduct(['name' => 'Nuevo nombre', 'code' => '', 'sale_price' => '7.50', 'category_id' => $newCategory->id]))
        ->assertSessionHasNoErrors();

    expect($product->fresh())
        ->name->toBe('Nuevo nombre')
        ->category_id->toBe($newCategory->id)
        ->and((float) $product->fresh()->sale_price)->toBe(7.5);
});

it('permite conservar su propio nombre y código al editar', function () {
    $product = Product::factory()->create(['name' => 'Salteña', 'code' => 'SAL-1']);

    $this->put(route('products.update', $product), validProduct(['name' => 'Salteña', 'code' => 'SAL-1', 'category_id' => $product->category_id]))
        ->assertSessionHasNoErrors();
});

it('no deja pasar a unidad entera un producto con stock decimal', function () {
    $product = Product::factory()->byWeight()->create(['stock' => 2.5]);

    $this->put(route('products.update', $product), validProduct(['name' => $product->name, 'code' => '', 'category_id' => $product->category_id, 'unit' => 'unidad', 'min_stock' => '1']))
        ->assertSessionHasErrors('unit');

    expect($product->fresh()->unit)->toBe(ProductUnit::Kilogramo);
});

it('activa y desactiva productos', function () {
    $product = Product::factory()->create();

    $this->patch(route('products.toggle', $product))->assertRedirect();
    expect($product->fresh()->is_active)->toBeFalse();

    $this->patch(route('products.toggle', $product))->assertRedirect();
    expect($product->fresh()->is_active)->toBeTrue();
});

it('muestra la página del listado con sus filtros', function () {
    Category::factory()->create(['name' => 'Bebidas']);

    $this->get(route('products.index'))->assertOk()->assertSee('Bebidas')->assertSee('data-datatable', false);
});

// ---- Listado con DataTables (servidor) -------------------------------------

it('el listado devuelve la estructura que espera DataTables', function () {
    Product::factory()->count(3)->create();

    dataTable($this, 'products.data', ['draw' => 7])
        ->assertOk()
        ->assertJsonPath('draw', 7)
        ->assertJsonPath('recordsTotal', 3)
        ->assertJsonPath('recordsFiltered', 3)
        ->assertJsonCount(3, 'data')
        ->assertJsonStructure(['data' => [['name', 'category', 'unit', 'purchase_price', 'sale_price', 'stock', 'status', 'actions']]]);
});

it('busca por nombre, código o categoría', function () {
    $bebidas = Category::factory()->create(['name' => 'Bebidas frías']);
    Product::factory()->create(['name' => 'Salteña de pollo', 'code' => 'SAL-9']);
    Product::factory()->create(['name' => 'Coca-Cola', 'code' => 'BEB-1', 'category_id' => $bebidas->id]);

    $byName = dataTable($this, 'products.data', ['search' => ['value' => 'salte']]);
    expect(dataTableText($byName))->toContain('Salteña de pollo')->not->toContain('Coca-Cola');
    $byName->assertJsonPath('recordsFiltered', 1)->assertJsonPath('recordsTotal', 2);

    expect(dataTableText(dataTable($this, 'products.data', ['search' => ['value' => 'BEB-1']])))
        ->toContain('Coca-Cola')->not->toContain('Salteña');

    expect(dataTableText(dataTable($this, 'products.data', ['search' => ['value' => 'frías']])))
        ->toContain('Coca-Cola')->not->toContain('Salteña');
});

it('la búsqueda trata % y _ como texto literal', function () {
    Product::factory()->create(['name' => 'Producto normal']);

    dataTable($this, 'products.data', ['search' => ['value' => '%']])->assertJsonPath('recordsFiltered', 0);
});

it('filtra por stock bajo, agotado, estado y categoría', function () {
    $cat = Category::factory()->create();
    Product::factory()->create(['name' => 'Producto sano', 'stock' => 50, 'min_stock' => 5]);
    Product::factory()->lowStock()->create(['name' => 'Producto justo']);
    Product::factory()->outOfStock()->create(['name' => 'Producto vacío']);
    Product::factory()->inactive()->create(['name' => 'Producto apagado']);
    Product::factory()->create(['name' => 'Producto de categoría', 'category_id' => $cat->id]);

    $bajo = dataTableText(dataTable($this, 'products.data', ['stock' => 'bajo']));
    expect($bajo)->toContain('Producto justo')->not->toContain('Producto sano')->not->toContain('Producto vacío');

    $agotado = dataTableText(dataTable($this, 'products.data', ['stock' => 'agotado']));
    expect($agotado)->toContain('Producto vacío')->not->toContain('Producto justo');

    $inactivos = dataTableText(dataTable($this, 'products.data', ['estado' => 'inactivos']));
    expect($inactivos)->toContain('Producto apagado')->not->toContain('Producto sano');

    $activos = dataTableText(dataTable($this, 'products.data', ['estado' => 'activos']));
    expect($activos)->not->toContain('Producto apagado')->toContain('Producto sano');

    $porCategoria = dataTableText(dataTable($this, 'products.data', ['categoria' => $cat->id]));
    expect($porCategoria)->toContain('Producto de categoría')->not->toContain('Producto sano');
});

it('ordena por la columna pedida y respeta la dirección', function () {
    Product::factory()->create(['name' => 'A barato', 'sale_price' => 1]);
    Product::factory()->create(['name' => 'B caro', 'sale_price' => 9]);
    Product::factory()->create(['name' => 'C medio', 'sale_price' => 5]);

    // Columna 4 = precio de venta (el administrador ve también el precio de compra).
    $desc = dataTable($this, 'products.data', ['order' => [['column' => 4, 'dir' => 'desc']]]);
    expect(array_map(fn ($r) => strip_tags($r['name']), $desc->json('data')))->toBe(['B caro', 'C medio', 'A barato']);

    $asc = dataTable($this, 'products.data', ['order' => [['column' => 4, 'dir' => 'asc']]]);
    expect(array_map(fn ($r) => strip_tags($r['name']), $asc->json('data')))->toBe(['A barato', 'C medio', 'B caro']);
});

it('ignora columnas de orden que no existen o no son ordenables', function () {
    Product::factory()->count(2)->create();

    dataTable($this, 'products.data', ['order' => [['column' => 99, 'dir' => 'asc']]])->assertOk();
    dataTable($this, 'products.data', ['order' => [['column' => 'name; DROP TABLE products', 'dir' => 'asc']]])->assertOk();
    dataTable($this, 'products.data', ['order' => [['column' => 0, 'dir' => 'sideways; --']]])->assertOk();

    expect(Product::count())->toBe(2);
});

it('pagina con start y length', function () {
    Product::factory()->count(30)->create();

    $page1 = dataTable($this, 'products.data', ['start' => 0, 'length' => 10]);
    $page3 = dataTable($this, 'products.data', ['start' => 20, 'length' => 10]);

    $page1->assertJsonCount(10, 'data')->assertJsonPath('recordsFiltered', 30);
    $page3->assertJsonCount(10, 'data');
    expect(dataTableText($page1))->not->toBe(dataTableText($page3));
});

it('limita el tamaño de página a 100 filas', function () {
    Product::factory()->count(5)->create();

    dataTable($this, 'products.data', ['length' => 100000])->assertJsonCount(5, 'data');
    dataTable($this, 'products.data', ['length' => -1])->assertJsonCount(5, 'data');
});

it('escapa el HTML de los nombres en las filas', function () {
    Product::factory()->create(['name' => '<script>alert(1)</script>']);

    $text = dataTableText(dataTable($this, 'products.data'));
    expect($text)->not->toContain('<script>')->toContain('&lt;script&gt;');
});

it('calcula el estado de stock del producto', function () {
    expect(Product::factory()->make(['stock' => 0, 'min_stock' => 5])->stockStatus())->toBe('agotado')
        ->and(Product::factory()->make(['stock' => 5, 'min_stock' => 5])->stockStatus())->toBe('bajo')
        ->and(Product::factory()->make(['stock' => 6, 'min_stock' => 5])->stockStatus())->toBe('normal');
});

it('el panel cuenta productos con stock bajo y agotados', function () {
    Product::factory()->create(['stock' => 50]);
    Product::factory()->lowStock()->create();
    Product::factory()->outOfStock()->create();
    Product::factory()->outOfStock()->inactive()->create();

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertViewHas('activeProducts', 3)
        ->assertViewHas('lowStockCount', 1)
        ->assertViewHas('outOfStockCount', 1);
});

it('muestra el historial de movimientos de un producto', function () {
    $this->post(route('products.store'), validProduct());
    $product = Product::first();

    $this->get(route('products.show', $product))
        ->assertOk()
        ->assertSee('Stock inicial')
        ->assertSee('Inventario inicial')
        ->assertSee('Comida preparada')
        ->assertSee($this->admin->name);
});

it('devuelve 404 para un producto inexistente', function () {
    $this->get(route('products.show', 9999))->assertNotFound();
});
