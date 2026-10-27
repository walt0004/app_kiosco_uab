<?php

namespace Database\Factories;

use App\Enums\ProductUnit;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $purchase = fake()->randomFloat(2, 1, 20);

        return [
            'name' => fake()->unique()->words(3, true),
            'code' => null,
            'category_id' => Category::factory(),
            'unit' => ProductUnit::Unidad,
            'purchase_price' => $purchase,
            'sale_price' => round($purchase * 1.4, 2),
            'stock' => 20,
            'min_stock' => 5,
            'is_active' => true,
        ];
    }

    public function withStock(float $stock): static
    {
        return $this->state(fn () => ['stock' => $stock]);
    }

    public function outOfStock(): static
    {
        return $this->state(fn () => ['stock' => 0]);
    }

    public function lowStock(): static
    {
        return $this->state(fn () => ['stock' => 3, 'min_stock' => 5]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }

    public function byWeight(): static
    {
        return $this->state(fn () => ['unit' => ProductUnit::Kilogramo, 'stock' => 10.5]);
    }
}
