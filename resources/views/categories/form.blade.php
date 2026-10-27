@php $isEdit = $category->exists; @endphp
<x-layouts.app :title="$isEdit ? 'Editar categoría' : 'Nueva categoría'">
    <form method="POST" action="{{ $isEdit ? route('categories.update', $category) : route('categories.store') }}"
          class="card max-w-xl space-y-5" novalidate>
        @csrf
        @if ($isEdit) @method('PUT') @endif

        <x-field name="name" label="Nombre" :required="true">
            <input id="name" name="name" type="text" maxlength="80" required value="{{ old('name', $category->name) }}"
                   class="input @error('name') input-error @enderror" placeholder="Ej.: Bebidas">
        </x-field>

        <label class="flex items-center gap-2 text-sm text-slate-700">
            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $category->is_active ?? true))
                   class="h-4 w-4 rounded border-slate-300 text-brand-700 focus:ring-brand-500">
            Categoría activa (se puede elegir al crear productos)
        </label>

        <div class="flex items-center gap-3">
            <button type="submit" class="btn-primary">{{ $isEdit ? 'Guardar cambios' : 'Crear categoría' }}</button>
            <a href="{{ route('categories.index') }}" class="btn-secondary">Cancelar</a>
        </div>
    </form>
</x-layouts.app>
