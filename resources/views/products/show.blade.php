@php
    use App\Support\Format;
    $isAdmin = auth()->user()->isAdmin();
    $margin = (float) $product->sale_price - (float) $product->purchase_price;
@endphp
<x-layouts.app :title="$product->name">
    <x-slot:actions>
        <a href="{{ route('products.index') }}" class="btn-secondary">Volver</a>
        @if ($isAdmin)
            <a href="{{ route('products.stock.create', $product) }}" class="btn-secondary">Ajustar stock</a>
            <a href="{{ route('products.edit', $product) }}" class="btn-primary">Editar</a>
        @endif
    </x-slot:actions>

    <div class="grid gap-5 lg:grid-cols-3">
        <section class="card lg:col-span-2">
            <div class="flex flex-wrap items-center gap-2">
                @if ($product->is_active)
                    <span class="badge bg-sky-100 text-sky-800">Activo</span>
                @else
                    <span class="badge bg-slate-200 text-slate-700">Inactivo</span>
                @endif
                <x-stock-badge :product="$product" />
            </div>

            <dl class="mt-5 grid gap-x-8 gap-y-4 text-sm sm:grid-cols-2">
                <div>
                    <dt class="text-slate-500">Categoría</dt>
                    <dd class="font-medium text-slate-900">{{ $product->category?->name ?? 'Sin categoría' }}</dd>
                </div>
                <div>
                    <dt class="text-slate-500">Código</dt>
                    <dd class="font-medium text-slate-900">{{ $product->code ?? 'Sin código' }}</dd>
                </div>
                <div>
                    <dt class="text-slate-500">Se vende por</dt>
                    <dd class="font-medium text-slate-900">{{ $product->unit->label() }}</dd>
                </div>
                @if ($isAdmin)
                    <div>
                        <dt class="text-slate-500">Precio de compra</dt>
                        <dd class="font-medium text-slate-900 tabular-nums">{{ Format::money($product->purchase_price) }}</dd>
                    </div>
                @endif
                <div>
                    <dt class="text-slate-500">Precio de venta</dt>
                    <dd class="font-medium text-slate-900 tabular-nums">{{ Format::money($product->sale_price) }}</dd>
                </div>
                @if ($isAdmin)
                    <div>
                        <dt class="text-slate-500">Ganancia por {{ mb_strtolower($product->unit->label()) }}</dt>
                        <dd class="font-medium tabular-nums {{ $margin < 0 ? 'text-red-600' : 'text-emerald-700' }}">{{ Format::money($margin) }}</dd>
                    </div>
                @endif
                <div>
                    <dt class="text-slate-500">Stock mínimo</dt>
                    <dd class="font-medium text-slate-900 tabular-nums">{{ Format::qty($product->min_stock) }} {{ $product->unit->short() }}</dd>
                </div>
            </dl>

            @if ($isAdmin)
                <form method="POST" action="{{ route('products.toggle', $product) }}" class="mt-6 border-t border-slate-100 pt-4">
                    @csrf
                    @method('PATCH')
                    <button type="submit" class="text-sm font-medium {{ $product->is_active ? 'text-red-700' : 'text-emerald-700' }} hover:underline">
                        {{ $product->is_active ? 'Desactivar producto' : 'Activar producto' }}
                    </button>
                    <p class="mt-1 text-xs text-slate-500">
                        {{ $product->is_active ? 'Un producto desactivado deja de aparecer en ventas, pero conserva su historial.' : 'Al activarlo volverá a aparecer en ventas.' }}
                    </p>
                </form>
            @endif
        </section>

        <section class="card flex flex-col justify-center text-center">
            <p class="text-sm text-slate-500">Stock actual</p>
            <p class="mt-1 text-5xl font-bold tabular-nums {{ $product->isOutOfStock() ? 'text-red-600' : ($product->isLowStock() ? 'text-amber-600' : 'text-slate-900') }}">
                {{ Format::qty($product->stock) }}
            </p>
            <p class="text-sm text-slate-500">{{ $product->unit->label() }}</p>
        </section>
    </div>

    <section class="card mt-5 overflow-hidden p-0">
        <h2 class="px-5 pt-5 text-base font-bold text-slate-900">Historial de stock</h2>

        <div class="mt-3 overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-left text-slate-600">
                    <tr>
                        <th class="px-5 py-3 font-semibold">Fecha</th>
                        <th class="px-5 py-3 font-semibold">Movimiento</th>
                        <th class="px-5 py-3 text-right font-semibold">Cantidad</th>
                        <th class="px-5 py-3 text-right font-semibold">Stock resultante</th>
                        <th class="px-5 py-3 font-semibold">Registrado por</th>
                        <th class="px-5 py-3 font-semibold">Motivo</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($movements as $movement)
                        @php $positive = (float) $movement->quantity > 0; @endphp
                        <tr>
                            <td class="whitespace-nowrap px-5 py-3 text-slate-600">{{ $movement->created_at->format('d/m/Y H:i') }}</td>
                            <td class="px-5 py-3">{{ $movement->type->label() }}</td>
                            <td class="px-5 py-3 text-right font-medium tabular-nums {{ $positive ? 'text-emerald-700' : 'text-red-600' }}">
                                {{ $positive ? '+' : '−' }}{{ Format::qty(abs((float) $movement->quantity)) }}
                            </td>
                            <td class="px-5 py-3 text-right tabular-nums">
                                <span class="text-slate-400">{{ Format::qty($movement->stock_before) }} →</span> {{ Format::qty($movement->stock_after) }}
                            </td>
                            <td class="px-5 py-3">{{ $movement->user->name }}</td>
                            <td class="px-5 py-3 text-slate-600">{{ $movement->reason ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-5 py-10 text-center text-slate-500">Este producto aún no tiene movimientos de stock.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    @if ($movements->hasPages())
        <div class="mt-5">{{ $movements->links() }}</div>
    @endif
</x-layouts.app>
