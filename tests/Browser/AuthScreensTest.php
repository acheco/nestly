<?php

use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\User;
use Laravel\Fortify\Features;

// Spec 001, R1 (D-091, D-098): the authentication screens render in Spanish, both the page
// (layout title, main button) and the browser tab title from <Head>. A real browser is needed
// because the tests have no SSR: the text only exists once Vue renders the page.

function tabTitle(string $title): string
{
    return $title.' - '.config('app.name');
}

it('shows the login screen in Spanish', function () {
    visit(route('login'))
        ->assertTitle(tabTitle('Iniciar sesión'))
        ->assertSee('Inicie sesión en su cuenta')
        ->assertSee('Iniciar sesión con una llave de acceso')
        ->assertSee('Recordarme')
        ->assertPresent('button[aria-label="Mostrar contraseña"]')
        ->assertNoJavaScriptErrors();
});

it('shows the team invitation notice on the login screen in Spanish', function () {
    $owner = User::factory()->create();
    $team = Team::factory()->create(['name' => 'Familia']);
    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);

    $invitation = TeamInvitation::factory()->create([
        'team_id' => $team->id,
        'email' => 'invited@example.com',
        'invited_by' => $owner->id,
    ]);

    visit(route('login', ['invitation' => $invitation->code]))
        ->assertSee('Inicie sesión para unirse al equipo "Familia".')
        ->assertNoJavaScriptErrors();
});

it('shows the registration screen in Spanish', function () {
    visit(route('register'))
        ->assertTitle(tabTitle('Registrarse'))
        ->assertSee('Cree una cuenta')
        ->assertSee('Crear cuenta')
        ->assertNoJavaScriptErrors();
});

it('shows the forgot password screen in Spanish', function () {
    visit(route('password.request'))
        ->assertTitle(tabTitle('Recuperar contraseña'))
        ->assertSee('Recuperar contraseña')
        ->assertSee('Enviar enlace de restablecimiento')
        ->assertNoJavaScriptErrors();
});

it('shows the reset password screen in Spanish', function () {
    visit(route('password.reset', ['token' => 'token', 'email' => 'ana@example.com']))
        ->assertTitle(tabTitle('Restablecer contraseña'))
        ->assertSee('Ingrese su nueva contraseña')
        ->assertSee('Restablecer contraseña')
        ->assertNoJavaScriptErrors();
});

it('shows the email verification screen in Spanish', function () {
    $this->actingAs(User::factory()->unverified()->create());

    visit(route('verification.notice'))
        ->assertTitle(tabTitle('Verificación del correo electrónico'))
        ->assertSee('Verificación del correo electrónico')
        ->assertSee('Reenviar correo de verificación')
        ->assertNoJavaScriptErrors();
});

it('shows the confirm password screen in Spanish', function () {
    $this->actingAs(User::factory()->create());

    visit(route('password.confirm'))
        ->assertTitle(tabTitle('Confirmar contraseña'))
        ->assertSee('Esta es una zona segura de la aplicación.')
        ->assertSee('Confirmar contraseña')
        ->assertNoJavaScriptErrors();
});

it('shows both two-factor challenge modes in Spanish', function () {
    $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());

    $user = User::factory()->withTwoFactor()->create();

    visit(route('login'))
        ->type('email', $user->email)
        ->type('password', 'password')
        ->click('[data-test="login-button"]')
        ->assertTitle(tabTitle('Autenticación de dos factores'))
        ->assertSee('Código de autenticación')
        ->click('iniciar sesión con un código de recuperación')
        ->assertSee('Código de recuperación')
        ->assertSee('iniciar sesión con un código de autenticación')
        ->assertNoJavaScriptErrors();
});
