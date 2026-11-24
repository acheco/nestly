<?php

use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\User;

// Spec 001, R1 (D-091, D-098, D-099): the app screens render in Spanish, both the page and the
// browser tab title. A real browser is needed because the tests have no SSR.

function appTabTitle(string $title): string
{
    return $title.' - '.config('app.name');
}

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->team = Team::factory()->create(['name' => 'Familia']);
    $this->team->members()->attach($this->user, ['role' => TeamRole::Owner->value]);
    $this->user->forceFill(['current_team_id' => $this->team->id])->save();
    // UserFactory's switchTeam() caches the personal team as currentTeam, and the browser
    // server runs in this process with this same instance: drop it so Familia is current.
    $this->user->unsetRelation('currentTeam');

    $this->actingAs($this->user);
});

it('shows the home screen in Spanish', function () {
    visit(route('dashboard', $this->team))
        ->assertTitle(appTabTitle('Inicio'))
        ->assertNoJavaScriptErrors();
});

// R11: the home screen shows the team and links to its management, without placeholder blocks.
// The assertions are scoped to the page content because the sidebar's TeamSwitcher also shows the name.
it('shows the team name and a link to its settings on the home screen', function () {
    visit(route('dashboard', $this->team))
        ->assertSeeIn('[data-test="home-team-name"]', 'Familia')
        ->assertSeeIn('[data-test="home-team-settings"]', 'Ajustes del equipo')
        ->assertAttribute('[data-test="home-team-settings"]', 'href', route('teams.edit', $this->team, false))
        ->assertNotPresent('[data-slot="sidebar-inset"] svg pattern')
        ->assertNoJavaScriptErrors();
});

it('shows the profile settings in Spanish', function () {
    visit(route('profile.edit'))
        ->assertTitle(appTabTitle('Ajustes de perfil'))
        ->assertSee('Actualice su nombre y su correo electrónico')
        ->assertSee('Nombre')
        ->assertNoJavaScriptErrors();
});

it('shows the security settings in Spanish', function () {
    visit(route('security.edit'))
        ->type('password', 'password')
        ->click('[data-test="confirm-password-button"]')
        ->assertTitle(appTabTitle('Ajustes de seguridad'))
        ->assertSee('Actualizar contraseña')
        ->assertNoJavaScriptErrors();
});

it('shows the appearance settings in Spanish', function () {
    visit(route('appearance.edit'))
        ->assertTitle(appTabTitle('Ajustes de apariencia'))
        ->assertSee('Claro')
        ->assertSee('Oscuro')
        ->assertSee('Sistema')
        ->assertNoJavaScriptErrors();
});

it('shows the teams list in Spanish', function () {
    visit(route('teams.index'))
        ->assertTitle(appTabTitle('Equipos'))
        ->assertSee('Gestione sus equipos y sus membresías')
        ->assertSee('Propietario')
        ->assertNoJavaScriptErrors();
});

it('shows the team settings in Spanish', function () {
    visit(route('teams.edit', $this->team))
        ->assertTitle(appTabTitle('Editar Familia'))
        ->assertSee('Ajustes del equipo')
        ->assertSee('Miembros del equipo')
        ->assertSee('Propietario')
        ->assertNoJavaScriptErrors();
});
