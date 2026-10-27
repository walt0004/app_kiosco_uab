@php $isAdmin = auth()->user()->isAdmin(); @endphp
<x-layouts.app title="Ventas">
    <x-slot:actions>
        <a href="{{ route('sales.create') }}" class="btn-primary">Nueva venta</a>
    </x-slot:actions>

    <form id="sale-filters" method="GET" action="{{ route('sales.index') }}" class="card mb-5 flex flex-wrap items-end gap-3">
        <div>
            <label for="from" class="mb-1.5 block text-sm font-medium text-slate-700">Desde</label>
            <input id="from" name="from" type="date" value="{{ $today }}" data-default="{{ $today }}" class="input">
        </div>
        <div>
            <label for="to" class="mb-1.5 block text-sm font-medium text-slate-700">Hasta</label>
            <input id="to" name="to" type="date" value="{{ $today }}" data-default="{{ $today }}" class="input">
        </div>
        @if ($isAdmin)
            <div>
                <label for="user" class="mb-1.5 block text-sm font-medium text-slate-700">Vendedor</label>
                <select id="user" name="user" class="input">
                    <option value="">Todos</option>
                    @foreach ($sellers as $seller)
                        <option value="{{ $seller->id }}">{{ $seller->name }}</option>
                    @endforeach
                </select>
            </div>
        @endif
        <div>
            <label for="method" class="mb-1.5 block text-sm font-medium text-slate-700">Pago</label>
            <select id="method" name="method" class="input">
                <option value="">Todos</option>
                @foreach ($methods as $method)
                    <option value="{{ $method->value }}">{{ $method->label() }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="status" class="mb-1.5 block text-sm font-medium text-slate-700">Estado</label>
            <select id="status" name="status" class="input">
                <option value="">Todos</option>
                <option value="completada">Completadas</option>
                <option value="anulada">Anuladas</option>
            </select>
        </div>
        <button type="button" data-dt-reset class="btn-secondary">Restablecer (hoy)</button>
    </form>

    <div class="mb-5 grid gap-4 sm:grid-cols-2">
        <div class="card">
            <p class="text-sm text-slate-500">Ventas completadas (según los filtros)</p>
            <p class="mt-1 text-3xl font-bold tabular-nums text-slate-900" data-dt-summary="count">…</p>
        </div>
        <div class="card">
            <p class="text-sm text-slate-500">Total vendido (según los filtros)</p>
            <p class="mt-1 text-3xl font-bold tabular-nums text-slate-900" data-dt-summary="total">…</p>
        </div>
    </div>

    <div class="card">
        <x-datatable
            :url="route('sales.data')"
            :order="[[0, 'desc']]"
            filters="#sale-filters"
            placeholder="Número o vendedor"
            :columns="[
                ['data' => 'number', 'title' => 'N°', 'orderable' => true],
                ['data' => 'created_at', 'title' => 'Fecha', 'orderable' => true],
                ['data' => 'seller', 'title' => 'Vendedor', 'orderable' => true],
                ['data' => 'method', 'title' => 'Pago', 'orderable' => true],
                ['data' => 'total', 'title' => 'Total', 'orderable' => true, 'className' => 'dt-right'],
                ['data' => 'status', 'title' => 'Estado', 'orderable' => true],
                ['data' => 'actions', 'title' => '', 'className' => 'dt-right'],
            ]" />
    </div>
</x-layouts.app>
