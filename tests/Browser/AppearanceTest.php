<?php

use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\User;
use Pest\Browser\Api\PendingAwaitablePage;

// Spec 001, R7: the `appearance` cookie is the only source of the theme. The server renders
// `<html>` from it, so the client must not override it with a stale localStorage value or the
// page flickers to the wrong theme after the app boots.

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->team = Team::factory()->create(['name' => 'Familia']);
    $this->team->members()->attach($this->user, ['role' => TeamRole::Owner->value]);
    $this->user->forceFill(['current_team_id' => $this->team->id])->save();

    $this->actingAs($this->user);
});

/**
 * Sets the cookie the way the app does and a conflicting localStorage value left by older
 * builds. The caller navigates afterwards so the server renders from the cookie.
 */
function seedAppearance(PendingAwaitablePage $page, string $cookie): void
{
    $page->script(<<<JS
        () => {
            document.cookie = 'appearance={$cookie};path=/;max-age=31536000;SameSite=Lax';
            localStorage.setItem('appearance', 'system');
        }
        JS);
}

it('keeps the dark cookie after the app boots on a light system', function () {
    $url = route('dashboard', $this->team);
    $page = visit($url)->inLightMode();

    seedAppearance($page, 'dark');

    $page->navigate($url)
        ->assertTitle('Inicio - '.config('app.name'))
        ->assertScript("document.documentElement.classList.contains('dark')", true)
        ->assertNoJavaScriptErrors();
});

it('keeps the light cookie after the app boots on a dark system', function () {
    $url = route('dashboard', $this->team);
    $page = visit($url)->inDarkMode();

    seedAppearance($page, 'light');

    $page->navigate($url)
        ->assertTitle('Inicio - '.config('app.name'))
        ->assertScript("document.documentElement.classList.contains('dark')", false)
        ->assertNoJavaScriptErrors();
});

it('marks the cookie appearance as the active tab in the settings', function () {
    $url = route('appearance.edit');
    $page = visit($url)->inLightMode();

    seedAppearance($page, 'dark');

    $page->navigate($url)
        ->assertAttribute('[data-test="appearance-tab-dark"]', 'aria-pressed', 'true')
        ->assertAttribute('[data-test="appearance-tab-system"]', 'aria-pressed', 'false')
        ->assertNoJavaScriptErrors();
});
