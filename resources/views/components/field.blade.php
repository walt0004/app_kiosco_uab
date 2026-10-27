@props(['name', 'label', 'hint' => null, 'required' => false])

<div {{ $attributes->class(['space-y-1.5']) }}>
    <label for="{{ $name }}" class="block text-sm font-medium text-slate-700">
        {{ $label }}@if ($required)<span class="text-red-600" aria-hidden="true"> *</span>@endif
    </label>

    {{ $slot }}

    @if ($hint)
        <p class="text-xs text-slate-500">{{ $hint }}</p>
    @endif

    @error($name)
        <p class="text-sm text-red-600" role="alert">{{ $message }}</p>
    @enderror
</div>
