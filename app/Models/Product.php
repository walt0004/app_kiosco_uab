<?php

namespace App\Models;

use App\Enums\ProductUnit;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    /**
     * "stock" NO es asignable en masa: solo cambia a través de StockService,
     * para que cada variación quede registrada en stock_movements.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'code',
        'category_id',
        'unit',
        'purchase_price',
        'sale_price',
        'min_stock',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'unit' => ProductUnit::class,
            'purchase_price' => 'decimal:2',
            'sale_price' => 'decimal:2',
            'stock' => 'decimal:3',
            'min_stock' => 'decimal:3',
            'is_active' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function isOutOfStock(): bool
    {
        return (float) $this->stock <= 0;
    }

    /** Stock en o por debajo del mínimo configurado (incluye agotado). */
    public function isLowStock(): bool
    {
        return (float) $this->stock <= (float) $this->min_stock;
    }

    /** Normal | bajo | agotado */
    public function stockStatus(): string
    {
        if ($this->isOutOfStock()) {
            return 'agotado';
        }

        return $this->isLowStock() ? 'bajo' : 'normal';
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('products.is_active', true);
    }

    /** Productos activos con stock igual o menor al mínimo. */
    public function scopeLowStock(Builder $query): Builder
    {
        return $query->where('products.is_active', true)->whereColumn('products.stock', '<=', 'products.min_stock');
    }

    /** Busca por nombre o código, escapando los comodines de LIKE. */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);

        if ($term === '') {
            return $query;
        }

        $like = '%'.addcslashes($term, '\\%_').'%';

        return $query->where(function (Builder $q) use ($like) {
            $q->where('name', 'like', $like)->orWhere('code', 'like', $like);
        });
    }
}
