@php
    $isAdmin = auth()->user()->isAdmin();
@endphp
<x-layouts.app title="Productos">
    @if ($isAdmin)
        <x-slot:actions>
            <a href="{{ route('products.create') }}" class="btn-primary">Nuevo producto</a>
        </x-slot:actions>
    @endif

    <form id="product-filters" method="GET" action="{{ route('products.index') }}" class="card mb-5 flex flex-wrap items-end gap-3">
        <div>
            <label for="categoria" class="mb-1.5 block text-sm font-medium text-slate-700">Categoría</label>
            <select id="categoria" name="categoria" class="input">
                <option value="">Todas</option>
                @foreach ($categories as $category)
                    <option value="{{ $category->id }}" @selected((string) $filters['categoria'] === (string) $category->id)>{{ $category->name }}</option>
                @endforeach
                <option value="ninguna" @selected($filters['categoria'] === 'ninguna')>Sin categoría</option>
            </select>
        </div>
        <div>
            <label for="estado" class="mb-1.5 block text-sm font-medium text-slate-700">Estado</label>
            <select id="estado" name="estado" class="input">
                <option value="">Todos</option>
                <option value="activos" @selected($filters['estado'] === 'activos')>Activos</option>
                <option value="inactivos" @selected($filters['estado'] === 'inactivos')>Inactivos</option>
            </select>
        </div>
        <div>
            <label for="stock" class="mb-1.5 block text-sm font-medium text-slate-700">Stock</label>
            <select id="stock" name="stock" class="input">
                <option value="">Todo</option>
                <option value="bajo" @selected($filters['stock'] === 'bajo')>Stock bajo</option>
                <option value="agotado" @selected($filters['stock'] === 'agotado')>Agotados</option>
            </select>
        </div>
        <button type="button" data-dt-reset class="btn-secondary">Limpiar filtros</button>
    </form>

    <div class="card">
        <x-datatable
            :url="route('products.data')"
            :columns="$columns"
            :order="[[0, 'asc']]"
            filters="#product-filters"
            placeholder="Nombre, código o categoría" />
    </div>
</x-layouts.app>
