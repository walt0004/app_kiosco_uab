<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create(['email' => 'admin@test.com']);
    $this->seller = User::factory()->seller()->create();
});

function userData(array $extra = []): array
{
    return array_merge([
        'name' => 'Nuevo Vendedor',
        'email' => 'nuevo@test.com',
        'role' => 'vendedor',
        'is_active' => 1,
        'password' => 'Clave12345',
        'password_confirmation' => 'Clave12345',
    ], $extra);
}

it('el administrador crea usuarios y estos pueden ingresar', function () {
    $this->actingAs($this->admin)->post(route('users.store'), userData())->assertRedirect(route('users.index'));

    $user = User::where('email', 'nuevo@test.com')->firstOrFail();
    expect(Hash::check('Clave12345', $user->password))->toBeTrue()->and($user->isAdmin())->toBeFalse();

    auth()->logout();
    $this->post(route('login.store'), ['email' => 'nuevo@test.com', 'password' => 'Clave12345'])->assertRedirect(route('dashboard'));
});

it('valida los datos del usuario', function () {
    $this->actingAs($this->admin);

    $this->post(route('users.store'), userData(['email' => 'admin@test.com']))->assertSessionHasErrors('email');
    $this->post(route('users.store'), userData(['password' => 'corta1', 'password_confirmation' => 'corta1']))->assertSessionHasErrors('password');
    $this->post(route('users.store'), userData(['password' => 'solo letras', 'password_confirmation' => 'solo letras']))->assertSessionHasErrors('password');
    $this->post(route('users.store'), userData(['password_confirmation' => 'otra']))->assertSessionHasErrors('password');
    $this->post(route('users.store'), userData(['role' => 'dueño']))->assertSessionHasErrors('role');
    $this->post(route('users.store'), userData(['password' => '', 'password_confirmation' => '']))->assertSessionHasErrors('password');
});

it('al editar, una contraseña vacía no se cambia', function () {
    $old = $this->seller->password;

    $this->actingAs($this->admin)->put(route('users.update', $this->seller), [
        'name' => 'Nombre Nuevo', 'email' => $this->seller->email, 'role' => 'vendedor', 'is_active' => 1,
        'password' => '', 'password_confirmation' => '',
    ])->assertSessionHasNoErrors();

    expect($this->seller->fresh()->name)->toBe('Nombre Nuevo')->and($this->seller->fresh()->password)->toBe($old);
});

it('el administrador no puede quitarse el rol ni desactivarse', function () {
    $this->actingAs($this->admin);

    $this->put(route('users.update', $this->admin), [
        'name' => $this->admin->name, 'email' => $this->admin->email, 'role' => 'vendedor', 'is_active' => 1,
    ])->assertSessionHasErrors();

    $this->put(route('users.update', $this->admin), [
        'name' => $this->admin->name, 'email' => $this->admin->email, 'role' => 'administrador',
    ])->assertSessionHasErrors();

    $this->patch(route('users.toggle', $this->admin))->assertSessionHas('error');

    $fresh = $this->admin->fresh();
    expect($fresh->isAdmin())->toBeTrue()->and($fresh->is_active)->toBeTrue();
});

it('desactiva a un vendedor y este ya no puede entrar', function () {
    $this->actingAs($this->admin)->patch(route('users.toggle', $this->seller))->assertSessionHas('success');
    expect($this->seller->fresh()->is_active)->toBeFalse();

    auth()->logout();
    $this->post(route('login.store'), ['email' => $this->seller->email, 'password' => 'password'])->assertSessionHasErrors();
    $this->assertGuest();
});

it('lista los usuarios por DataTables y solo para el administrador', function () {
    expect(dataTableText(dataTable($this->actingAs($this->admin), 'users.data')))->toContain('admin@test.com');

    $this->actingAs($this->seller);
    $this->get(route('users.index'))->assertForbidden();
    $this->getJson(route('users.data'))->assertForbidden();
    $this->post(route('users.store'), userData())->assertForbidden();
    $this->patch(route('users.toggle', $this->admin))->assertForbidden();
});

describe('mi cuenta', function () {
    it('cambia la contraseña con la actual correcta', function () {
        $this->actingAs($this->seller)->put(route('profile.update'), [
            'current_password' => 'password', 'password' => 'Nueva12345', 'password_confirmation' => 'Nueva12345',
        ])->assertRedirect(route('profile.edit'))->assertSessionHas('success');

        expect(Hash::check('Nueva12345', $this->seller->fresh()->password))->toBeTrue();
    });

    it('rechaza una contraseña actual incorrecta, débil o igual', function () {
        $this->actingAs($this->seller);

        $this->put(route('profile.update'), ['current_password' => 'mal', 'password' => 'Nueva12345', 'password_confirmation' => 'Nueva12345'])
            ->assertSessionHasErrors('current_password');
        $this->put(route('profile.update'), ['current_password' => 'password', 'password' => 'abc', 'password_confirmation' => 'abc'])
            ->assertSessionHasErrors('password');
        $this->put(route('profile.update'), ['current_password' => 'password', 'password' => 'password', 'password_confirmation' => 'password'])
            ->assertSessionHasErrors('password');

        expect(Hash::check('password', $this->seller->fresh()->password))->toBeTrue();
    });

    it('requiere iniciar sesión', function () {
        $this->get(route('profile.edit'))->assertRedirect(route('login'));
    });
});
