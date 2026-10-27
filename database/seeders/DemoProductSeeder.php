<?php

namespace Database\Seeders;

use App\Enums\ProductUnit;
use App\Enums\StockMovementType;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Services\StockService;
use Illuminate\Database\Seeder;

/**
 * Productos de ejemplo (solo para pruebas locales). Puedes borrarlos o
 * desactivarlos desde el sistema.
 */
class DemoProductSeeder extends Seeder
{
    public function run(StockService $stock): void
    {
        $admin = User::where('email', 'admin@kiosco.test')->firstOrFail();

        $categories = collect(['Comida preparada', 'Bebidas', 'Snacks y golosinas', 'Café y té'])
            ->mapWithKeys(fn ($name) => [$name => Category::firstOrCreate(['name' => $name], ['is_active' => true])]);

        $products = [
            // nombre, código, categoría, unidad, compra, venta, stock inicial, mínimo
            ['Empanada de queso', 'EMP-001', 'Comida preparada', ProductUnit::Unidad, 2.50, 4.00, 40, 10],
            ['Salteña', 'SAL-001', 'Comida preparada', ProductUnit::Unidad, 4.00, 6.00, 30, 10],
            ['Coca-Cola 500 ml', 'BEB-001', 'Bebidas', ProductUnit::Unidad, 5.00, 7.00, 24, 6],
            ['Agua mineral 600 ml', 'BEB-002', 'Bebidas', ProductUnit::Unidad, 2.00, 3.50, 4, 6],
            ['Galletas de chocolate', 'GAL-001', 'Snacks y golosinas', ProductUnit::Paquete, 3.00, 5.00, 15, 5],
            ['Café en grano', null, 'Café y té', ProductUnit::Kilogramo, 80.00, 110.00, 2.5, 1],
            ['Chicle', 'CHI-001', 'Snacks y golosinas', ProductUnit::Unidad, 0.50, 1.00, 0, 20],
        ];

        foreach ($products as [$name, $code, $category, $unit, $purchase, $sale, $initial, $min]) {
            $existing = Product::where('name', $name)->first();

            if ($existing) {
                // Productos de una instalación anterior: solo se completa la categoría.
                if ($existing->category_id === null || $existing->category?->name === 'General') {
                    $existing->update(['category_id' => $categories[$category]->id]);
                }

                continue;
            }

            $product = Product::create([
                'name' => $name,
                'code' => $code,
                'category_id' => $categories[$category]->id,
                'unit' => $unit,
                'purchase_price' => $purchase,
                'sale_price' => $sale,
                'min_stock' => $min,
                'is_active' => true,
            ]);

            if ($initial > 0) {
                $stock->apply($product, StockMovementType::Inicial, (float) $initial, $admin, 'Inventario inicial');
            }
        }
    }
}
