<x-layouts.app title="Mi contraseña">
    <form method="POST" action="{{ route('profile.update') }}" class="card max-w-lg space-y-5" novalidate>
        @csrf
        @method('PUT')

        <p class="text-sm text-slate-600">Cambia la contraseña de <span class="font-semibold">{{ auth()->user()->email }}</span>.</p>

        <x-field name="current_password" label="Contraseña actual" :required="true">
            <input id="current_password" name="current_password" type="password" autocomplete="current-password" required
                   class="input @error('current_password') input-error @enderror">
        </x-field>

        <x-field name="password" label="Contraseña nueva" :required="true" hint="Mínimo 8 caracteres, con letras y números.">
            <input id="password" name="password" type="password" autocomplete="new-password" required
                   class="input @error('password') input-error @enderror">
        </x-field>

        <x-field name="password_confirmation" label="Repite la contraseña nueva" :required="true">
            <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required class="input">
        </x-field>

        <button type="submit" class="btn-primary">Guardar contraseña</button>
    </form>
</x-layouts.app>
