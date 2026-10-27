<?php

namespace App\Services;

use App\Enums\PaymentMethod;
use App\Enums\SaleStatus;
use App\Enums\StockMovementType;
use App\Exceptions\BusinessRuleException;
use App\Exceptions\InsufficientStockException;
use App\Models\CashSession;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use App\Support\Money;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class SaleService
{
    public function __construct(private readonly StockService $stock) {}

    /**
     * Registra una venta completa en una sola transacción: valida cada producto,
     * descuenta el stock y guarda la venta con sus líneas. Si algo falla
     * (por ejemplo falta stock) no se guarda nada.
     *
     * Los precios SIEMPRE se toman de la base de datos, nunca de lo que envíe el navegador.
     *
     * @param  array<int, array{product_id: int|string, quantity: float|int|string}>  $items
     *
     * @throws BusinessRuleException
     * @throws InsufficientStockException
     */
    public function create(User $user, array $items, PaymentMethod $method, ?string $requestToken = null): Sale
    {
        if ($requestToken !== null && ($existing = Sale::where('request_token', $requestToken)->first())) {
            return $existing;
        }

        $lines = $this->mergeLines($items);

        if ($lines === []) {
            throw new BusinessRuleException('Agrega al menos un producto a la venta.');
        }

        try {
            return DB::transaction(function () use ($user, $lines, $method, $requestToken) {
                // Bloquea el turno para que no se pueda vender mientras otro lo está cerrando.
                $session = CashSession::open()->where('user_id', $user->getKey())->lockForUpdate()->first();

                if ($session === null) {
                    throw new BusinessRuleException('Debes abrir tu caja antes de registrar ventas.');
                }

                // Orden fijo por id: evita bloqueos cruzados entre dos ventas simultáneas.
                ksort($lines);

                $products = Product::query()->whereIn('id', array_keys($lines))->get()->keyBy('id');

                $sale = Sale::create([
                    'cash_session_id' => $session->getKey(),
                    'user_id' => $user->getKey(),
                    'total' => 0,
                    'payment_method' => $method,
                    'status' => SaleStatus::Completada,
                    'request_token' => $requestToken,
                ]);

                $totalCents = 0;

                foreach ($lines as $productId => $quantity) {
                    /** @var Product|null $product */
                    $product = $products->get($productId);

                    if ($product === null) {
                        throw new BusinessRuleException('Uno de los productos ya no existe.');
                    }

                    if (! $product->is_active) {
                        throw new BusinessRuleException("«{$product->name}» está desactivado y no se puede vender.");
                    }

                    if (! $product->unit->allowsDecimals() && floor($quantity) !== $quantity) {
                        throw new BusinessRuleException("«{$product->name}» se vende por ".mb_strtolower($product->unit->label()).': la cantidad debe ser un número entero.');
                    }

                    $subtotalCents = Money::lineCents($product->sale_price, $quantity);
                    $totalCents += $subtotalCents;

                    $sale->items()->create([
                        'product_id' => $product->getKey(),
                        'product_name' => $product->name,
                        'unit' => $product->unit,
                        'quantity' => $quantity,
                        'unit_price' => $product->sale_price,
                        'purchase_price' => $product->purchase_price,
                        'subtotal' => Money::fromCents($subtotalCents),
                    ]);

                    // Lanza InsufficientStockException si no alcanza: revierte toda la venta.
                    $this->stock->apply($product, StockMovementType::Venta, -$quantity, $user, 'Venta '.$sale->number(), $sale->getKey());
                }

                $sale->update(['total' => Money::fromCents($totalCents)]);

                return $sale->load('items');
            });
        } catch (UniqueConstraintViolationException) {
            // Doble clic: el mismo token ya registró esta venta.
            if ($requestToken !== null && ($existing = Sale::where('request_token', $requestToken)->first())) {
                return $existing;
            }

            throw new BusinessRuleException('No se pudo registrar la venta. Inténtalo de nuevo.');
        }
    }

    /**
     * Anula una venta: devuelve el stock y deja constancia de quién, cuándo y por qué.
     * Solo se pueden anular ventas de turnos que aún están abiertos, para no alterar
     * un cierre de caja que ya se entregó.
     *
     * @throws BusinessRuleException
     */
    public function void(Sale $sale, User $admin, string $reason): Sale
    {
        $reason = trim($reason);

        if (! $admin->isAdmin()) {
            throw new BusinessRuleException('Solo un administrador puede anular ventas.');
        }

        if (mb_strlen($reason) < 5) {
            throw new BusinessRuleException('Indica el motivo de la anulación (al menos 5 caracteres).');
        }

        return DB::transaction(function () use ($sale, $admin, $reason) {
            /** @var Sale $locked */
            $locked = Sale::query()->whereKey($sale->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->isVoided()) {
                throw new BusinessRuleException('Esta venta ya fue anulada.');
            }

            $session = CashSession::query()->whereKey($locked->cash_session_id)->lockForUpdate()->firstOrFail();

            if (! $session->isOpen()) {
                throw new BusinessRuleException(
                    'No se puede anular: el turno de caja de esta venta ya fue cerrado. Registra el ajuste en el siguiente turno.'
                );
            }

            $items = $locked->items()->with('product')->orderBy('product_id')->get();

            foreach ($items as $item) {
                $this->stock->apply(
                    $item->product,
                    StockMovementType::Anulacion,
                    (float) $item->quantity,
                    $admin,
                    'Anulación de '.$locked->number().' - '.$reason,
                    $locked->getKey(),
                );
            }

            $locked->update([
                'status' => SaleStatus::Anulada,
                'voided_by' => $admin->getKey(),
                'voided_at' => now(),
                'void_reason' => $reason,
            ]);

            $sale->refresh();

            return $locked;
        });
    }

    /**
     * Junta líneas repetidas del mismo producto y descarta cantidades inválidas.
     *
     * @param  array<int, array{product_id: int|string, quantity: float|int|string}>  $items
     * @return array<int, float> product_id => cantidad
     */
    private function mergeLines(array $items): array
    {
        $lines = [];

        foreach ($items as $item) {
            $id = (int) ($item['product_id'] ?? 0);
            $quantity = round((float) ($item['quantity'] ?? 0), 3);

            if ($id <= 0) {
                continue;
            }

            if ($quantity <= 0) {
                throw new BusinessRuleException('Las cantidades deben ser mayores a cero.');
            }

            $lines[$id] = round(($lines[$id] ?? 0) + $quantity, 3);
        }

        return $lines;
    }
}
