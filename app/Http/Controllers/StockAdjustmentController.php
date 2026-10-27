<?php

namespace App\Http\Controllers;

use App\Enums\StockMovementType;
use App\Exceptions\InsufficientStockException;
use App\Http\Requests\AdjustStockRequest;
use App\Models\Product;
use App\Services\StockService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class StockAdjustmentController extends Controller
{
    public function __construct(private readonly StockService $stock) {}

    public function create(Product $product): View
    {
        return view('products.stock', ['product' => $product]);
    }

    public function store(AdjustStockRequest $request, Product $product): RedirectResponse
    {
        $quantity = (float) $request->validated('quantity');
        $reason = $request->validated('reason');
        $user = $request->user();

        try {
            $movement = match ($request->validated('operation')) {
                'entrada' => $this->stock->apply($product, StockMovementType::Entrada, $quantity, $user, $reason),
                'salida' => $this->stock->apply($product, StockMovementType::Salida, -$quantity, $user, $reason),
                'correccion' => $this->stock->correct($product, $quantity, $user, $reason),
            };
        } catch (InsufficientStockException $e) {
            return back()->withInput()->withErrors(['quantity' => $e->getMessage()]);
        }

        if ($movement === null) {
            return redirect()
                ->route('products.show', $product)
                ->with('info', 'El stock contado coincide con el registrado; no se hizo ningún cambio.');
        }

        return redirect()
            ->route('products.show', $product)
            ->with('success', 'Stock actualizado correctamente.');
    }
}
