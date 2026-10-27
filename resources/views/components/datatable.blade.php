{{--
    Tabla DataTables.
    - Modo servidor:  <x-datatable :columns="[...]" url="..." />
    - Modo cliente:   <x-datatable> con <thead>/<tbody> propios dentro del slot.
    columns: [['data' => 'name', 'title' => 'Producto', 'orderable' => true, 'className' => 'text-right'], ...]
--}}
@props([
    'columns' => [],
    'url' => null,
    'order' => [],
    'filters' => null,
    'pageLength' => 25,
    'placeholder' => '',
])

<div data-dt-wrapper class="overflow-x-auto">
    <table data-datatable
           @if ($url)
               data-url="{{ $url }}"
               data-columns="{{ json_encode(array_map(fn ($c) => array_merge(['orderable' => false], $c), $columns)) }}"
           @endif
           data-order="{{ json_encode($order) }}"
           @if ($filters) data-filters="{{ $filters }}" @endif
           data-page-length="{{ $pageLength }}"
           data-search-placeholder="{{ $placeholder }}"
           {{ $attributes->class(['display w-full text-sm']) }}>
        @if ($url)
            <thead>
                <tr>
                    @foreach ($columns as $column)
                        <th>{{ $column['title'] }}</th>
                    @endforeach
                </tr>
            </thead>
        @else
            {{ $slot }}
        @endif
    </table>
</div>
