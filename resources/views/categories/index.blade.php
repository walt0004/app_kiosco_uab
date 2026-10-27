<x-layouts.app title="Categorías">
    <x-slot:actions>
        <a href="{{ route('categories.create') }}" class="btn-primary">Nueva categoría</a>
    </x-slot:actions>

    <p class="mb-4 max-w-2xl text-sm text-slate-600">
        Las categorías agrupan los productos (bebidas, comida, snacks…) y permiten ver reportes por categoría.
    </p>

    <div class="card">
        <x-datatable
            :url="route('categories.data')"
            :order="[[0, 'asc']]"
            placeholder="Nombre de la categoría"
            :columns="[
                ['data' => 'name', 'title' => 'Categoría', 'orderable' => true],
                ['data' => 'products_count', 'title' => 'Productos', 'orderable' => true, 'className' => 'dt-right'],
                ['data' => 'status', 'title' => 'Estado', 'orderable' => true],
                ['data' => 'actions', 'title' => '', 'className' => 'dt-right'],
            ]" />
    </div>
</x-layouts.app>
