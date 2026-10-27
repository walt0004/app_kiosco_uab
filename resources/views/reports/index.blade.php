<x-layouts.app title="Reportes">
    <p class="mb-5 max-w-2xl text-sm text-slate-600">
        Elige un reporte, ajusta las fechas y los filtros, y descárgalo en PDF si lo necesitas. Las ventas anuladas nunca se cuentan.
    </p>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        @foreach ($definitions as $slug => $definition)
            <a href="{{ route('reports.show', $slug) }}" class="card block transition hover:ring-brand-300">
                <h2 class="text-base font-bold text-slate-900">{{ $definition['title'] }}</h2>
                <p class="mt-1 text-sm text-slate-600">{{ $definition['description'] }}</p>
            </a>
        @endforeach
    </div>
</x-layouts.app>
