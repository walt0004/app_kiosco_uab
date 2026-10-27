<div class="flex items-center justify-end gap-3 whitespace-nowrap text-sm">
    <a href="{{ route('categories.edit', $category) }}" class="font-medium text-brand-700 hover:underline">Editar</a>

    <form method="POST" action="{{ route('categories.toggle', $category) }}">
        @csrf
        @method('PATCH')
        <button type="submit" class="font-medium {{ $category->is_active ? 'text-amber-700' : 'text-emerald-700' }} hover:underline">
            {{ $category->is_active ? 'Desactivar' : 'Activar' }}
        </button>
    </form>

    @if ((int) $category->products_count === 0)
        <form method="POST" action="{{ route('categories.destroy', $category) }}"
              onsubmit="return confirm('¿Eliminar la categoría «{{ e($category->name) }}»? Esta acción no se puede deshacer.')">
            @csrf
            @method('DELETE')
            <button type="submit" class="font-medium text-red-700 hover:underline">Eliminar</button>
        </form>
    @endif
</div>
