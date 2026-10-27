@php $isEdit = $user->exists; @endphp
<x-layouts.app :title="$isEdit ? 'Editar usuario' : 'Nuevo usuario'">
    <form method="POST" action="{{ $isEdit ? route('users.update', $user) : route('users.store') }}" class="card max-w-2xl space-y-5" novalidate autocomplete="off">
        @csrf
        @if ($isEdit) @method('PUT') @endif

        <div class="grid gap-5 sm:grid-cols-2">
            <x-field name="name" label="Nombre completo" :required="true">
                <input id="name" name="name" type="text" maxlength="100" required value="{{ old('name', $user->name) }}"
                       class="input @error('name') input-error @enderror">
            </x-field>

            <x-field name="email" label="Correo electrónico" :required="true" hint="Con este correo ingresa al sistema.">
                <input id="email" name="email" type="email" maxlength="150" required value="{{ old('email', $user->email) }}"
                       class="input @error('email') input-error @enderror" autocomplete="off">
            </x-field>

            <x-field name="role" label="Rol" :required="true">
                <select id="role" name="role" class="input @error('role') input-error @enderror">
                    @foreach ($roles as $role)
                        <option value="{{ $role->value }}" @selected(old('role', $user->role?->value) === $role->value)>{{ $role->label() }}</option>
                    @endforeach
                </select>
            </x-field>

            <div class="flex items-end pb-2">
                <label class="flex items-center gap-2 text-sm text-slate-700">
                    <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $user->is_active ?? true))
                           class="h-4 w-4 rounded border-slate-300 text-brand-700 focus:ring-brand-500">
                    Usuario activo (puede ingresar)
                </label>
            </div>
            @error('is_active')
                <p class="text-sm text-red-600 sm:col-span-2" role="alert">{{ $message }}</p>
            @enderror
        </div>

        <div class="grid gap-5 border-t border-slate-100 pt-5 sm:grid-cols-2">
            <x-field name="password" :label="$isEdit ? 'Nueva contraseña' : 'Contraseña'" :required="! $isEdit"
                     :hint="$isEdit ? 'Déjala vacía para no cambiarla.' : 'Mínimo 8 caracteres, con letras y números.'">
                <input id="password" name="password" type="password" autocomplete="new-password"
                       class="input @error('password') input-error @enderror">
            </x-field>

            <x-field name="password_confirmation" label="Repite la contraseña">
                <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" class="input">
            </x-field>
        </div>

        <div class="flex items-center gap-3">
            <button type="submit" class="btn-primary">{{ $isEdit ? 'Guardar cambios' : 'Crear usuario' }}</button>
            <a href="{{ route('users.index') }}" class="btn-secondary">Cancelar</a>
        </div>
    </form>
</x-layouts.app>
