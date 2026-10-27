<?php

namespace App\Services;

use App\Enums\StockMovementType;
use App\Exceptions\InsufficientStockException;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Único punto por el que cambia el stock de un producto.
 *
 * - Bloquea la fila del producto dentro de una transacción, de modo que dos
 *   vendedores no puedan vender a la vez la última unidad.
 * - Nunca permite stock negativo.
 * - Deja siempre un registro en stock_movements (quién, cuánto, antes/después).
 *
 * Es seguro llamarlo dentro de otra transacción (p. ej. al registrar una venta).
 */
class StockService
{
    /**
     * Suma (delta > 0) o resta (delta < 0) existencias.
     *
     * @throws InsufficientStockException si el stock resultante sería negativo
     */
    public function apply(
        Product $product,
        StockMovementType $type,
        float $delta,
        User $user,
        ?string $reason = null,
        ?int $saleId = null,
    ): StockMovement {
        $delta = round($delta, 3);

        if ($delta === 0.0) {
            throw new InvalidArgumentException('La cantidad del movimiento no puede ser cero.');
        }

        return DB::transaction(function () use ($product, $type, $delta, $user, $reason, $saleId) {
            /** @var Product $locked */
            $locked = Product::query()->whereKey($product->getKey())->lockForUpdate()->firstOrFail();

            $before = round((float) $locked->stock, 3);
            $after = round($before + $delta, 3);

            if ($after < 0) {
                throw new InsufficientStockException($locked, abs($delta), $before);
            }

            $locked->stock = $after;
            $locked->save();

            $movement = StockMovement::create([
                'product_id' => $locked->getKey(),
                'user_id' => $user->getKey(),
                'sale_id' => $saleId,
                'type' => $type,
                'quantity' => $delta,
                'stock_before' => $before,
                'stock_after' => $after,
                'reason' => $reason,
            ]);

            $product->refresh();

            return $movement;
        });
    }

    /**
     * Corrige el inventario al valor realmente contado.
     * Devuelve null si el stock ya coincide (no hay nada que registrar).
     */
    public function correct(Product $product, float $counted, User $user, ?string $reason = null): ?StockMovement
    {
        if ($counted < 0) {
            throw new InvalidArgumentException('El stock contado no puede ser negativo.');
        }

        $movement = DB::transaction(function () use ($product, $counted, $user, $reason) {
            $locked = Product::query()->whereKey($product->getKey())->lockForUpdate()->firstOrFail();

            $delta = round($counted - (float) $locked->stock, 3);

            if ($delta === 0.0) {
                return null;
            }

            return $this->apply($locked, StockMovementType::Correccion, $delta, $user, $reason);
        });

        $product->refresh();

        return $movement;
    }
}
