<?php

use App\Models\User;

it('muestra la pantalla de ingreso', function () {
    $this->get(route('login'))->assertOk()->assertSee('Ingresa a tu cuenta');
});

it('redirige a los visitantes al ingreso', function () {
    $this->get('/panel')->assertRedirect(route('login'));
    $this->get('/productos')->assertRedirect(route('login'));
    $this->get('/')->assertRedirect('/panel');
});

it('permite ingresar con credenciales correctas', function () {
    $user = User::factory()->create(['email' => 'ana@uab.test']);

    $this->post(route('login.store'), ['email' => 'ana@uab.test', 'password' => 'password'])
        ->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($user);
});

it('rechaza una contraseña incorrecta', function () {
    User::factory()->create(['email' => 'ana@uab.test']);

    $this->from(route('login'))
        ->post(route('login.store'), ['email' => 'ana@uab.test', 'password' => 'incorrecta'])
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors(['email' => __('auth.failed')]);

    $this->assertGuest();
});

it('rechaza a un usuario desactivado aunque la contraseña sea correcta', function () {
    User::factory()->inactive()->create(['email' => 'baja@uab.test']);

    $this->from(route('login'))
        ->post(route('login.store'), ['email' => 'baja@uab.test', 'password' => 'password'])
        ->assertSessionHasErrors(['email' => __('auth.inactive')]);

    $this->assertGuest();
});

it('exige correo y contraseña', function () {
    $this->post(route('login.store'), [])->assertSessionHasErrors(['email', 'password']);
});

it('bloquea el ingreso tras demasiados intentos fallidos', function () {
    User::factory()->create(['email' => 'ana@uab.test']);

    foreach (range(1, 5) as $i) {
        $this->post(route('login.store'), ['email' => 'ana@uab.test', 'password' => 'mal']);
    }

    // Aun con la contraseña correcta, el sexto intento queda bloqueado.
    $this->post(route('login.store'), ['email' => 'ana@uab.test', 'password' => 'password'])
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('cierra la sesión', function () {
    $this->actingAs(User::factory()->create())
        ->post(route('logout'))
        ->assertRedirect(route('login'));

    $this->assertGuest();
});

it('expulsa a un usuario que fue desactivado con la sesión abierta', function () {
    $user = User::factory()->create();

    $this->actingAs($user);
    $user->update(['is_active' => false]);

    $this->get(route('dashboard'))->assertRedirect(route('login'));
    $this->assertGuest();
});
