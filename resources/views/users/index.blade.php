<x-layouts.app title="Usuarios">
    <x-slot:actions>
        <a href="{{ route('users.create') }}" class="btn-primary">Nuevo usuario</a>
    </x-slot:actions>

    <div class="card">
        <x-datatable
            :url="route('users.data')"
            :order="[[0, 'asc']]"
            placeholder="Nombre o correo"
            :columns="[
                ['data' => 'name', 'title' => 'Nombre', 'orderable' => true],
                ['data' => 'email', 'title' => 'Correo', 'orderable' => true],
                ['data' => 'role', 'title' => 'Rol', 'orderable' => true],
                ['data' => 'status', 'title' => 'Estado', 'orderable' => true],
                ['data' => 'actions', 'title' => '', 'className' => 'dt-right'],
            ]" />
    </div>
</x-layouts.app>
