<div class="flex items-center justify-end gap-3 whitespace-nowrap text-sm">
    <a href="{{ route('users.edit', $user) }}" class="font-medium text-brand-700 hover:underline">Editar</a>
    @unless ($isSelf)
        <form method="POST" action="{{ route('users.toggle', $user) }}">
            @csrf
            @method('PATCH')
            <button type="submit" class="font-medium {{ $user->is_active ? 'text-amber-700' : 'text-emerald-700' }} hover:underline">
                {{ $user->is_active ? 'Desactivar' : 'Activar' }}
            </button>
        </form>
    @endunless
</div>
