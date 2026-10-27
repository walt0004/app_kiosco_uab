<x-layouts.app title="Historial de turnos">
    <x-slot:actions>
        <a href="{{ route('cash.index') }}" class="btn-secondary">Volver a caja</a>
    </x-slot:actions>

    <div class="card">
        <x-datatable
            :url="route('cash.data')"
            :order="[[0, 'desc']]"
            placeholder="Usuario o número de turno"
            :columns="[
                ['data' => 'id', 'title' => 'Turno', 'orderable' => true],
                ['data' => 'user', 'title' => 'Usuario', 'orderable' => true],
                ['data' => 'opened_at', 'title' => 'Apertura', 'orderable' => true],
                ['data' => 'closed_at', 'title' => 'Cierre', 'orderable' => true],
                ['data' => 'opening_amount', 'title' => 'Inicial', 'orderable' => true, 'className' => 'dt-right'],
                ['data' => 'expected_cash', 'title' => 'Esperado', 'orderable' => true, 'className' => 'dt-right'],
                ['data' => 'counted_cash', 'title' => 'Contado', 'orderable' => true, 'className' => 'dt-right'],
                ['data' => 'difference', 'title' => 'Diferencia', 'orderable' => true],
                ['data' => 'status', 'title' => 'Estado', 'orderable' => true],
                ['data' => 'actions', 'title' => '', 'className' => 'dt-right'],
            ]" />
    </div>
</x-layouts.app>
