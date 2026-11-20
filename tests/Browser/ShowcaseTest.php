<?php

use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\User;

// Spec 001, R6 (D-102, D-103): the dev showcase renders every section in Spanish, formats
// amounts in a real browser and shows the four toast types. Its visual approval is manual.

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->team = Team::factory()->create(['name' => 'Familia']);
    $this->team->members()->attach($this->user, ['role' => TeamRole::Owner->value]);
    $this->user->forceFill(['current_team_id' => $this->team->id])->save();

    $this->actingAs($this->user);
});

it('shows the showcase sections, amounts and toasts', function () {
    $page = visit('/dev/showcase')
        ->assertTitle('Página de muestra - '.config('app.name'))
        ->assertSee('Botones')
        ->assertSee('Tarjetas')
        ->assertSee('Montos')
        ->assertSee('Campos')
        ->assertSee('Notificaciones')
        // Copied from the Vitest expectations, so Chromium must format like Node:
        // tests/js/money.test.ts:9 and tests/js/money.test.ts:21.
        ->assertSee('RD$999,999,999,999.99')
        ->assertSee('-US$1,234.50');

    $page->click('[data-test="toast-success"]')->assertSee('Los cambios se guardaron.');
    $page->click('[data-test="toast-error"]')->assertSee('No se pudieron guardar los cambios.');
    $page->click('[data-test="toast-info"]')->assertSee('Hay información nueva disponible.');
    $page->click('[data-test="toast-warning"]')->assertSee('Revise los datos antes de continuar.');

    $page->assertNoJavaScriptErrors();
});
