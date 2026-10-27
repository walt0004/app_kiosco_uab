<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function edit(): View
    {
        return view('profile.edit');
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'string', 'confirmed', 'different:current_password', Password::min(8)->letters()->numbers()],
        ], [
            'password.confirmed' => 'Las contraseñas nuevas no coinciden.',
            'password.different' => 'La contraseña nueva debe ser distinta de la actual.',
            'password.min' => 'La contraseña debe tener al menos 8 caracteres.',
            'current_password.required' => 'Escribe tu contraseña actual.',
        ]);

        $request->user()->update(['password' => $data['password']]);

        return redirect()->route('profile.edit')->with('success', 'Tu contraseña fue actualizada.');
    }
}
