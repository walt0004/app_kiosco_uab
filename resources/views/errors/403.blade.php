<x-layouts.guest title="Sin permiso">
    <h2 class="mb-1 text-lg font-bold text-slate-900">No tienes permiso para esto</h2>
    <p class="mb-6 text-sm text-slate-600">{{ $exception->getMessage() ?: 'Tu rol no permite realizar esta acción. Si crees que es un error, habla con el administrador.' }}</p>
    <a href="{{ url('/panel') }}" class="btn-primary w-full">Volver al panel</a>
</x-layouts.guest>
