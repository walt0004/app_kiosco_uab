<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\User;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->seller = User::factory()->seller()->create();
});

it('lista las categorías con su cantidad de productos', function () {
    $drinks = Category::factory()->create(['name' => 'Bebidas']);
    Product::factory()->count(2)->create(['category_id' => $drinks->id]);

    $json = dataTable($this->actingAs($this->admin), 'categories.data', ['search' => ['value' => 'Bebi']]);

    expect(dataTableText($json))->toContain('Bebidas');
    expect($json->json('data.0.products_count'))->toBe(2);
});

it('crea una categoría', function () {
    $this->actingAs($this->admin)->post(route('categories.store'), ['name' => '  Bebidas   frías ', 'is_active' => 1])
        ->assertRedirect(route('categories.index'));

    expect(Category::where('name', 'Bebidas frías')->exists())->toBeTrue();
});

it('no permite nombres vacíos ni repetidos', function () {
    Category::factory()->create(['name' => 'Bebidas']);
    $this->actingAs($this->admin);

    $this->post(route('categories.store'), ['name' => ''])->assertSessionHasErrors('name');
    $this->post(route('categories.store'), ['name' => 'Bebidas'])->assertSessionHasErrors('name');
});

it('edita una categoría conservando su propio nombre', function () {
    $category = Category::factory()->create(['name' => 'Bebidas']);

    $this->actingAs($this->admin)->put(route('categories.update', $category), ['name' => 'Bebidas', 'is_active' => 1])
        ->assertSessionHasNoErrors();
    $this->put(route('categories.update', $category), ['name' => 'Líquidos', 'is_active' => 1]);

    expect($category->fresh()->name)->toBe('Líquidos');
});

it('activa y desactiva una categoría', function () {
    $category = Category::factory()->create();

    $this->actingAs($this->admin)->patch(route('categories.toggle', $category))->assertRedirect();

    expect($category->fresh()->is_active)->toBeFalse();
});

it('no elimina categorías con productos pero sí las vacías', function () {
    $used = Category::factory()->create();
    Product::factory()->create(['category_id' => $used->id]);
    $empty = Category::factory()->create();

    $this->actingAs($this->admin)->delete(route('categories.destroy', $used))->assertSessionHas('error');
    $this->delete(route('categories.destroy', $empty))->assertSessionHas('success');

    expect(Category::find($used->id))->not->toBeNull()->and(Category::find($empty->id))->toBeNull();
});

it('el vendedor no accede a las categorías', function () {
    $category = Category::factory()->create();
    $this->actingAs($this->seller);

    $this->get(route('categories.index'))->assertForbidden();
    $this->getJson(route('categories.data'))->assertForbidden();
    $this->post(route('categories.store'), ['name' => 'X'])->assertForbidden();
    $this->delete(route('categories.destroy', $category))->assertForbidden();
});

it('el producto exige una categoría válida', function () {
    $inactive = Category::factory()->create(['is_active' => false]);
    $this->actingAs($this->admin);
    $base = ['name' => 'Chocolate', 'unit' => 'unidad', 'purchase_price' => 1, 'sale_price' => 2, 'min_stock' => 1, 'stock' => 0];

    $this->post(route('products.store'), $base)->assertSessionHasErrors('category_id');
    $this->post(route('products.store'), $base + ['category_id' => 9999])->assertSessionHasErrors('category_id');
    $this->post(route('products.store'), $base + ['category_id' => $inactive->id])->assertSessionHasErrors('category_id');

    $ok = Category::factory()->create();
    $this->post(route('products.store'), $base + ['category_id' => $ok->id])->assertSessionHasNoErrors();
    expect(Product::where('name', 'Chocolate')->first()->category_id)->toBe($ok->id);
});

it('filtra los productos por categoría', function () {
    $a = Category::factory()->create();
    $b = Category::factory()->create();
    Product::factory()->create(['name' => 'Producto A', 'category_id' => $a->id]);
    Product::factory()->create(['name' => 'Producto B', 'category_id' => $b->id]);

    $text = dataTableText(dataTable($this->actingAs($this->seller), 'products.data', ['categoria' => $a->id]));

    expect($text)->toContain('Producto A')->not->toContain('Producto B');
});
