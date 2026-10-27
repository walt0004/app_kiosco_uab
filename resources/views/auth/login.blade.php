<x-layouts.guest title="Ingresar">
    <h2 class="mb-1 text-lg font-bold text-slate-900">Ingresa a tu cuenta</h2>
    <p class="mb-6 text-sm text-slate-500">Usa el correo y la contraseña que te dio el administrador.</p>

    <form method="POST" action="{{ route('login.store') }}" class="space-y-5" novalidate>
        @csrf

        <x-field name="email" label="Correo electrónico">
            <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="username"
                   class="input @error('email') input-error @enderror" placeholder="tucorreo@uab.edu.bo">
        </x-field>

        <x-field name="password" label="Contraseña">
            <input id="password" name="password" type="password" required autocomplete="current-password"
                   class="input @error('password') input-error @enderror">
        </x-field>

        <label class="flex items-center gap-2 text-sm text-slate-600">
            <input type="checkbox" name="remember" value="1" class="h-4 w-4 rounded border-slate-300 text-brand-700 focus:ring-brand-500">
            Mantener la sesión abierta en este equipo
        </label>

        <button type="submit" class="btn-primary w-full">Ingresar</button>
    </form>
</x-layouts.guest>
