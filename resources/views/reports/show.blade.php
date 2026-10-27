@php
    use App\Support\ReportCell;
    $has = fn (string $filter) => in_array($filter, $used, true);
    $columns = $report['columns'];
@endphp
<x-layouts.app :title="$report['title']">
    <x-slot:actions>
        <a href="{{ route('reports.index') }}" class="btn-secondary">Todos los reportes</a>
        <a href="{{ route('reports.pdf', ['report' => $report['slug']] + $filters->toQuery()) }}" class="btn-primary">Descargar PDF</a>
    </x-slot:actions>

    <form method="GET" action="{{ route('reports.show', $report['slug']) }}" class="card mb-5 space-y-4"
          x-data="{ from: @js($filters->from->toDateString()), to: @js($filters->to->toDateString()) }">
        <div class="flex flex-wrap items-end gap-3">
            @if ($has('date'))
                <div>
                    <label for="from" class="mb-1.5 block text-sm font-medium text-slate-700">Desde</label>
                    <input id="from" name="from" type="date" x-model="from" class="input">
                </div>
                <div>
                    <label for="to" class="mb-1.5 block text-sm font-medium text-slate-700">Hasta</label>
                    <input id="to" name="to" type="date" x-model="to" class="input">
                </div>
            @endif

            @if ($has('user'))
                <div>
                    <label for="user" class="mb-1.5 block text-sm font-medium text-slate-700">Usuario</label>
                    <select id="user" name="user" class="input">
                        <option value="">Todos</option>
                        @foreach ($users as $user)
                            <option value="{{ $user->id }}" @selected($filters->userId === $user->id)>{{ $user->name }}</option>
                        @endforeach
                    </select>
                </div>
            @endif

            @if ($has('category'))
                <div>
                    <label for="category" class="mb-1.5 block text-sm font-medium text-slate-700">Categoría</label>
                    <select id="category" name="category" class="input">
                        <option value="">Todas</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}" @selected($filters->categoryId === $category->id)>{{ $category->name }}</option>
                        @endforeach
                    </select>
                </div>
            @endif

            @if ($has('method'))
                <div>
                    <label for="method" class="mb-1.5 block text-sm font-medium text-slate-700">Método de pago</label>
                    <select id="method" name="method" class="input">
                        <option value="">Todos</option>
                        @foreach ($methods as $method)
                            <option value="{{ $method->value }}" @selected($filters->method === $method)>{{ $method->label() }}</option>
                        @endforeach
                    </select>
                </div>
            @endif

            @if ($has('group'))
                <div>
                    <label for="group" class="mb-1.5 block text-sm font-medium text-slate-700">Agrupar por</label>
                    <select id="group" name="group" class="input">
                        <option value="dia" @selected($filters->group === 'dia')>Día</option>
                        <option value="semana" @selected($filters->group === 'semana')>Semana</option>
                        <option value="mes" @selected($filters->group === 'mes')>Mes</option>
                    </select>
                </div>
            @endif

            @if ($has('order'))
                <div>
                    <label for="order" class="mb-1.5 block text-sm font-medium text-slate-700">Ordenar por</label>
                    <select id="order" name="order" class="input">
                        <option value="cantidad" @selected($filters->order === 'cantidad')>Cantidad vendida</option>
                        <option value="ingresos" @selected($filters->order === 'ingresos')>Ingresos</option>
                        <option value="utilidad" @selected($filters->order === 'utilidad')>Utilidad</option>
                    </select>
                </div>
            @endif

            @if ($has('top'))
                <div>
                    <label for="top" class="mb-1.5 block text-sm font-medium text-slate-700">Mostrar</label>
                    <select id="top" name="top" class="input">
                        @foreach ([10 => 'Los 10 primeros', 20 => 'Los 20 primeros', 50 => 'Los 50 primeros', 0 => 'Todos'] as $value => $label)
                            <option value="{{ $value }}" @selected($filters->top === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            @endif

            <button type="submit" class="btn-primary">Aplicar</button>
        </div>

        @if ($has('date'))
            <div class="flex flex-wrap items-center gap-2 text-sm">
                <span class="text-slate-500">Rango rápido:</span>
                @foreach ($ranges as $label => [$rangeFrom, $rangeTo])
                    <button type="button" class="rounded-full border border-slate-300 px-3 py-1 font-medium text-slate-700 hover:bg-slate-50"
                            @click="from = '{{ $rangeFrom }}'; to = '{{ $rangeTo }}'; $nextTick(() => $el.closest('form').requestSubmit())">{{ $label }}</button>
                @endforeach
            </div>
        @endif

        @error('to')
            <p class="text-sm text-red-600" role="alert">{{ $message }}</p>
        @enderror
    </form>

    @if ($report['subtitle'])
        <p class="mb-4 text-sm text-slate-600">{{ $report['subtitle'] }}</p>
    @endif

    @if ($report['cards'])
        <div class="mb-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            @foreach ($report['cards'] as $card)
                <div class="card">
                    <p class="text-sm text-slate-500">{{ $card['label'] }}</p>
                    <p class="mt-1 text-2xl font-bold tabular-nums text-slate-900">{{ $card['value'] }}</p>
                </div>
            @endforeach
        </div>
    @endif

    @if ($report['chart'])
        <section class="card mb-5">
            <div class="{{ $report['chart']['type'] === 'doughnut' ? 'mx-auto max-w-md' : '' }} h-72">
                <canvas data-chart="{{ json_encode($report['chart']) }}" aria-label="Gráfico: {{ $report['title'] }}" role="img"></canvas>
            </div>
        </section>
    @endif

    <section class="card">
        @if (count($report['rows']) === 0)
            <p class="py-10 text-center text-sm text-slate-500">No hay datos para los filtros elegidos. Prueba con otro rango de fechas.</p>
        @else
            <x-datatable :order="[]" :page-length="25" placeholder="Filtrar esta tabla">
                <thead>
                    <tr>
                        @foreach ($columns as $column)
                            <th class="{{ ReportCell::isNumeric($column['type']) ? 'dt-right' : '' }}">{{ $column['label'] }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($report['rows'] as $row)
                        <tr>
                            @foreach ($columns as $column)
                                <td class="{{ ReportCell::isNumeric($column['type']) ? 'dt-right tabular-nums' : '' }}"
                                    data-order="{{ ReportCell::sortValue($column['type'], $row[$column['key']] ?? null) }}">{{ ReportCell::format($column['type'], $row[$column['key']] ?? null) }}</td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
                @if ($report['totals'])
                    <tfoot>
                        <tr class="font-semibold">
                            @foreach ($columns as $column)
                                <td class="{{ ReportCell::isNumeric($column['type']) ? 'dt-right tabular-nums' : '' }}">{{ array_key_exists($column['key'], $report['totals']) ? ReportCell::format($column['type'], $report['totals'][$column['key']]) : '' }}</td>
                            @endforeach
                        </tr>
                    </tfoot>
                @endif
            </x-datatable>
        @endif
    </section>
</x-layouts.app>
