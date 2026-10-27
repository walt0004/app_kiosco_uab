@php
    use App\Support\Format;
    $isAdmin = $isAdmin ?? false;
@endphp
<x-layouts.app title="Panel">
    <p class="mb-5 text-slate-600">Hola, <span class="font-semibold">{{ auth()->user()->name }}</span>.</p>

    {{-- Estado de la caja del usuario --}}
    @if ($cashSession)
        <div class="mb-5 flex flex-wrap items-center justify-between gap-3 rounded-xl bg-emerald-50 px-5 py-4 ring-1 ring-emerald-200">
            <div>
                <p class="font-semibold text-emerald-900">Tu caja está abierta</p>
                <p class="text-sm text-emerald-800">Desde el {{ $cashSession->opened_at->format('d/m/Y H:i') }}</p>
            </div>
            <a href="{{ route('sales.create') }}" class="btn-primary">Registrar una venta</a>
        </div>
    @else
        <div class="mb-5 flex flex-wrap items-center justify-between gap-3 rounded-xl bg-amber-50 px-5 py-4 ring-1 ring-amber-200">
            <div>
                <p class="font-semibold text-amber-900">Tu caja está cerrada</p>
                <p class="text-sm text-amber-800">Ábrela con el efectivo inicial para poder vender.</p>
            </div>
            <a href="{{ route('cash.index') }}" class="btn-primary">Abrir caja</a>
        </div>
    @endif

    @if ($isAdmin)
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div class="card">
                <p class="text-sm text-slate-500">Ventas de hoy</p>
                <p class="mt-1 text-3xl font-bold tabular-nums text-slate-900">{{ Format::money($todayTotals['total']) }}</p>
                <p class="mt-1 text-xs text-slate-500">{{ $todayTotals['count'] }} venta(s) · ticket promedio {{ Format::money($todayTotals['average']) }}</p>
            </div>
            <div class="card">
                <p class="text-sm text-slate-500">Utilidad de hoy</p>
                <p class="mt-1 text-3xl font-bold tabular-nums {{ $todayTotals['profit'] < 0 ? 'text-red-600' : 'text-emerald-700' }}">{{ Format::money($todayTotals['profit']) }}</p>
                <p class="mt-1 text-xs text-slate-500">Ventas menos costo de los productos</p>
            </div>
            <div class="card">
                <p class="text-sm text-slate-500">Ventas del mes</p>
                <p class="mt-1 text-3xl font-bold tabular-nums text-slate-900">{{ Format::money($monthTotals['total']) }}</p>
                <p class="mt-1 text-xs text-slate-500">{{ $monthTotals['count'] }} venta(s)</p>
            </div>
            <div class="card">
                <p class="text-sm text-slate-500">Utilidad del mes</p>
                <p class="mt-1 text-3xl font-bold tabular-nums {{ $monthTotals['profit'] < 0 ? 'text-red-600' : 'text-emerald-700' }}">{{ Format::money($monthTotals['profit']) }}</p>
            </div>
        </div>

        <div class="mt-5 grid gap-5 lg:grid-cols-3">
            <section class="card lg:col-span-2">
                <h2 class="mb-3 text-base font-bold text-slate-900">Ventas de los últimos 7 días</h2>
                <div class="h-64"><canvas data-chart="{{ json_encode($dailyChart) }}" role="img" aria-label="Ventas de los últimos 7 días"></canvas></div>
            </section>

            <section class="card">
                <h2 class="mb-3 text-base font-bold text-slate-900">Cobros por método (este mes)</h2>
                @if ($methodChart)
                    <div class="h-64"><canvas data-chart="{{ json_encode($methodChart) }}" role="img" aria-label="Cobros por método de pago"></canvas></div>
                @else
                    <p class="py-16 text-center text-sm text-slate-500">Aún no hay ventas este mes.</p>
                @endif
            </section>
        </div>

        <div class="mt-5 grid gap-5 lg:grid-cols-3">
            <section class="card lg:col-span-2">
                <h2 class="mb-3 text-base font-bold text-slate-900">Productos más vendidos del mes</h2>
                @if ($topChart)
                    <div class="h-60"><canvas data-chart="{{ json_encode($topChart) }}" role="img" aria-label="Productos más vendidos del mes"></canvas></div>
                @else
                    <p class="py-16 text-center text-sm text-slate-500">Aún no hay ventas este mes.</p>
                @endif
            </section>

            <section class="card">
                <h2 class="mb-3 text-base font-bold text-slate-900">Cajas abiertas ahora</h2>
                @forelse ($openSessions as $open)
                    <a href="{{ route('cash.show', $open) }}" class="flex items-center justify-between border-b border-slate-100 py-2.5 text-sm last:border-0 hover:bg-slate-50">
                        <span class="font-medium text-slate-900">{{ $open->user->name }}</span>
                        <span class="text-slate-500">desde {{ $open->opened_at->format('H:i') }}</span>
                    </a>
                @empty
                    <p class="py-8 text-center text-sm text-slate-500">No hay cajas abiertas.</p>
                @endforelse
            </section>
        </div>
    @else
        <div class="grid gap-4 sm:grid-cols-2">
            <div class="card">
                <p class="text-sm text-slate-500">Mis ventas de hoy</p>
                <p class="mt-1 text-3xl font-bold tabular-nums text-slate-900">{{ $mine['count'] }}</p>
            </div>
            <div class="card">
                <p class="text-sm text-slate-500">Total que vendí hoy</p>
                <p class="mt-1 text-3xl font-bold tabular-nums text-slate-900">{{ Format::money($mine['total']) }}</p>
            </div>
        </div>
    @endif

    <h2 class="mb-3 mt-8 text-lg font-bold text-slate-900">Inventario</h2>

    <div class="grid gap-4 sm:grid-cols-3">
        <a href="{{ route('products.index', ['estado' => 'activos']) }}" class="card block transition hover:ring-brand-300">
            <p class="text-sm text-slate-500">Productos activos</p>
            <p class="mt-1 text-3xl font-bold text-slate-900">{{ $activeProducts }}</p>
        </a>
        <a href="{{ route('products.index', ['stock' => 'bajo']) }}" class="card block transition hover:ring-amber-300">
            <p class="text-sm text-slate-500">Con stock bajo</p>
            <p class="mt-1 text-3xl font-bold {{ $lowStockCount > 0 ? 'text-amber-600' : 'text-slate-900' }}">{{ $lowStockCount }}</p>
        </a>
        <a href="{{ route('products.index', ['stock' => 'agotado']) }}" class="card block transition hover:ring-red-300">
            <p class="text-sm text-slate-500">Agotados</p>
            <p class="mt-1 text-3xl font-bold {{ $outOfStockCount > 0 ? 'text-red-600' : 'text-slate-900' }}">{{ $outOfStockCount }}</p>
        </a>
    </div>

    <section class="card mt-5">
        <h2 class="mb-4 text-base font-bold text-slate-900">Productos que necesitan reposición</h2>

        @forelse ($lowStockProducts as $product)
            @if ($loop->first)
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="text-left text-slate-500">
                            <tr>
                                <th class="py-2 pr-4 font-medium">Producto</th>
                                <th class="py-2 pr-4 text-right font-medium">Stock</th>
                                <th class="py-2 pr-4 text-right font-medium">Mínimo</th>
                                <th class="py-2 font-medium">Estado</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
            @endif
                            <tr>
                                <td class="py-2.5 pr-4">
                                    <a href="{{ route('products.show', $product) }}" class="font-medium text-brand-700 hover:underline">{{ $product->name }}</a>
                                </td>
                                <td class="py-2.5 pr-4 text-right tabular-nums">{{ Format::qty($product->stock) }} {{ $product->unit->short() }}</td>
                                <td class="py-2.5 pr-4 text-right tabular-nums text-slate-500">{{ Format::qty($product->min_stock) }}</td>
                                <td class="py-2.5"><x-stock-badge :product="$product" /></td>
                            </tr>
            @if ($loop->last)
                        </tbody>
                    </table>
                </div>
            @endif
        @empty
            <p class="py-6 text-center text-sm text-slate-500">Todo en orden: ningún producto está por debajo de su stock mínimo.</p>
        @endforelse
    </section>
</x-layouts.app>
