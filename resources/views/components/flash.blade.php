@php
    $messages = [
        'success' => 'border-emerald-300 bg-emerald-50 text-emerald-900',
        'info' => 'border-sky-300 bg-sky-50 text-sky-900',
        'error' => 'border-red-300 bg-red-50 text-red-900',
    ];
@endphp

@foreach ($messages as $key => $classes)
    @if (session($key))
        <div class="mb-5 flex items-start justify-between gap-4 rounded-lg border px-4 py-3 text-sm {{ $classes }}"
             role="{{ $key === 'error' ? 'alert' : 'status' }}"
             x-data="{ open: true }" x-show="open">
            <p>{{ session($key) }}</p>
            <button type="button" class="font-semibold opacity-70 hover:opacity-100" @click="open = false" aria-label="Cerrar mensaje">✕</button>
        </div>
    @endif
@endforeach
