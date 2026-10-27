<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Http\Requests\SaveUserRequest;
use App\Models\User;
use App\Support\DataTable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(): View
    {
        return view('users.index');
    }

    public function data(Request $request): JsonResponse
    {
        return DataTable::of(User::query(), $request)
            ->searchable(['name', 'email'])
            ->orderable([0 => 'name', 1 => 'email', 2 => 'role', 3 => 'is_active'])
            ->defaultOrder('name')
            ->response(fn (User $user) => [
                'name' => e($user->name).($user->id === $request->user()->id ? ' <span class="text-xs text-slate-500">(tú)</span>' : ''),
                'email' => e($user->email),
                'role' => e($user->role->label()),
                'status' => view('components.status-badge', ['active' => $user->is_active])->render(),
                'actions' => view('users._actions', ['user' => $user, 'isSelf' => $user->id === $request->user()->id])->render(),
            ]);
    }

    public function create(): View
    {
        return view('users.form', [
            'user' => new User(['role' => Role::Vendedor, 'is_active' => true]),
            'roles' => Role::cases(),
        ]);
    }

    public function store(SaveUserRequest $request): RedirectResponse
    {
        $user = User::create($request->validated());

        return redirect()->route('users.index')->with('success', "Usuario «{$user->name}» creado. Ya puede ingresar con su correo y contraseña.");
    }

    public function edit(User $user): View
    {
        return view('users.form', ['user' => $user, 'roles' => Role::cases()]);
    }

    public function update(SaveUserRequest $request, User $user): RedirectResponse
    {
        $data = $request->validated();

        // Contraseña vacía = no cambiarla.
        if (empty($data['password'])) {
            unset($data['password']);
        }

        $user->update($data);

        return redirect()->route('users.index')->with('success', 'Usuario actualizado correctamente.');
    }

    public function toggle(Request $request, User $user): RedirectResponse
    {
        if ($user->id === $request->user()->id) {
            return back()->with('error', 'No puedes desactivar tu propia cuenta.');
        }

        $user->update(['is_active' => ! $user->is_active]);

        return back()->with('success', $user->is_active
            ? "«{$user->name}» ahora puede ingresar al sistema."
            : "«{$user->name}» fue desactivado y ya no puede ingresar.");
    }
}
