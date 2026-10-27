<?php

namespace App\Http\Requests;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class SaveUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => is_string($this->name) ? trim(preg_replace('/\s+/', ' ', $this->name)) : $this->name,
            'email' => is_string($this->email) ? mb_strtolower(trim($this->email)) : $this->email,
            'is_active' => $this->boolean('is_active'),
        ]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        /** @var User|null $target */
        $target = $this->route('user');

        return [
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'string', 'email', 'max:150', Rule::unique('users', 'email')->ignore($target)],
            'role' => ['required', Rule::enum(Role::class)],
            'is_active' => ['boolean'],
            'password' => [
                $target ? 'nullable' : 'required',
                'string',
                'confirmed',
                Password::min(8)->letters()->numbers(),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.unique' => 'Ya existe un usuario con ese correo.',
            'password.required' => 'Escribe una contraseña inicial para el usuario.',
            'password.confirmed' => 'Las contraseñas no coinciden.',
            'password.min' => 'La contraseña debe tener al menos 8 caracteres.',
        ];
    }

    /**
     * Un administrador no puede quitarse a sí mismo el rol ni desactivarse,
     * para no dejar el sistema sin administradores.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                /** @var User|null $target */
                $target = $this->route('user');

                if ($target === null || $target->id !== $this->user()->id) {
                    return;
                }

                if ($this->input('role') !== Role::Administrador->value) {
                    $validator->errors()->add('role', 'No puedes quitarte a ti mismo el rol de administrador.');
                }

                if (! $this->boolean('is_active')) {
                    $validator->errors()->add('is_active', 'No puedes desactivar tu propia cuenta.');
                }
            },
        ];
    }
}
