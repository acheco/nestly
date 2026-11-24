<?php

use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\User;

// Spec 001, R9 (D-096): the menu links only to pages that exist. Below md (767 px) the mobile
// header and the bottom bar show and the sidebar does not; from md (768 px) it is the other way
// round. The active entry carries aria-current, everything works with the keyboard, and the bar
// never covers the end of the page.

const MOBILE_WIDTH = 767;
const DESKTOP_WIDTH = 768;

/** A real page-level key press (keydown + keyup), so Tab moves the focus like a user would. */
function pressKey(mixed $page, string $key): mixed
{
    return $page->withKeyDown($key, fn () => null);
}

/** Presses Tab until the element matching the selector has the focus, or fails. */
function tabUntilFocused(mixed $page, string $selector, int $limit = 40): mixed
{
    for ($presses = 0; $presses < $limit; $presses++) {
        pressKey($page, 'Tab');

        if ($page->script("() => document.activeElement?.matches('{$selector}') ?? false") === true) {
            return $page;
        }
    }

    throw new RuntimeException("Tab never reached [{$selector}] in {$limit} presses.");
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

it('shows Inicio in the sidebar and no starter kit links at 768 px', function () {
    visit(route('dashboard', $this->team))
        ->resize(DESKTOP_WIDTH, 900)
        ->assertSeeIn('[data-sidebar="sidebar"]', 'Inicio')
        ->assertDontSee('Repository')
        ->assertDontSee('Documentation')
        ->assertNotPresent('a[href*="github.com/laravel/vue-starter-kit"]')
        ->assertNotPresent('a[href*="laravel.com/docs/starter-kits"]')
        ->assertNoJavaScriptErrors();
});

it('shows the mobile header and the bottom bar at 767 px, not the sidebar', function () {
    visit(route('dashboard', $this->team))
        ->resize(MOBILE_WIDTH, 900)
        ->assertVisible('[data-test="bottom-nav"]')
        ->assertSeeIn('[data-test="bottom-nav"]', 'Inicio')
        ->assertSeeIn('[data-test="bottom-nav"]', 'Más')
        ->assertVisible('[data-test="mobile-header"]')
        ->assertSeeIn('[data-test="mobile-header"]', config('app.name'))
        ->assertSeeIn('[data-test="mobile-header"] [data-test="team-switcher-trigger"]', 'Familia')
        ->assertMissing('[data-sidebar="sidebar"]')
        ->assertNoJavaScriptErrors();
});

it('shows the sidebar at 768 px, not the mobile header or the bottom bar', function () {
    visit(route('dashboard', $this->team))
        ->resize(DESKTOP_WIDTH, 900)
        ->assertVisible('[data-sidebar="sidebar"]')
        ->assertMissing('[data-test="bottom-nav"]')
        ->assertMissing('[data-test="mobile-header"]')
        ->assertNoJavaScriptErrors();
});

it('marks the active entry with aria-current in the bar and in the sidebar', function () {
    visit(route('dashboard', $this->team))
        ->resize(MOBILE_WIDTH, 900)
        ->assertAttribute('[data-test="bottom-nav-entry"]', 'aria-current', 'page')
        ->resize(DESKTOP_WIDTH, 900)
        ->assertAttribute('[data-sidebar="sidebar"] [data-test="nav-main-entry"]', 'aria-current', 'page')
        ->assertNoJavaScriptErrors();

    visit(route('profile.edit'))
        ->resize(MOBILE_WIDTH, 900)
        ->assertAttributeMissing('[data-test="bottom-nav-entry"]', 'aria-current')
        ->resize(DESKTOP_WIDTH, 900)
        ->assertAttributeMissing('[data-sidebar="sidebar"] [data-test="nav-main-entry"]', 'aria-current')
        ->assertNoJavaScriptErrors();
});

it('reaches and activates every bar entry with the keyboard', function () {
    $page = visit(route('profile.edit'))->resize(MOBILE_WIDTH, 900);

    /** @var list<string> $hrefs */
    $hrefs = $page->script(
        "() => [...document.querySelectorAll('[data-test=\"bottom-nav-entry\"]')].map((entry) => entry.getAttribute('href'))",
    );

    // Today only Inicio is in the bar (D-096); every entry a later spec adds is walked too.
    expect($hrefs)->toContain(route('dashboard', $this->team, false));

    foreach ($hrefs as $href) {
        $page = visit(route('profile.edit'))->resize(MOBILE_WIDTH, 900);

        tabUntilFocused($page, "[data-test=\"bottom-nav-entry\"][href=\"{$href}\"]");
        pressKey($page, 'Enter');

        $page->assertPathIs($href)
            ->assertNoJavaScriptErrors();
    }

    tabUntilFocused($page, '[data-test="bottom-nav-more"]');
});

it('opens and closes Más with the keyboard', function () {
    $page = visit(route('dashboard', $this->team))->resize(MOBILE_WIDTH, 900);

    tabUntilFocused($page, '[data-test="bottom-nav-more"]');
    pressKey($page, 'Enter');

    $page->assertVisible('[data-test="more-panel"]')
        ->assertSeeIn('[data-test="more-panel-settings"]', 'Ajustes')
        ->assertSeeIn('[data-test="more-panel-logout"]', 'Cerrar sesión')
        ->assertNotPresent('[data-test="more-panel"] [data-test="team-switcher-trigger"]');

    pressKey($page, 'Escape');

    $page->assertMissing('[data-test="more-panel"]')
        ->assertScript("() => document.activeElement?.matches('[data-test=\"bottom-nav-more\"]') ?? false", true)
        ->assertNoJavaScriptErrors();
});

it('closes Más when an entry inside it navigates, since the layout persists', function () {
    $page = visit(route('dashboard', $this->team))->resize(MOBILE_WIDTH, 900);

    tabUntilFocused($page, '[data-test="bottom-nav-more"]');
    pressKey($page, 'Enter');
    $page->assertVisible('[data-test="more-panel"]');

    tabUntilFocused($page, '[data-test="more-panel-settings"]');
    pressKey($page, 'Enter');

    $page->assertPathIs(route('profile.edit', [], false))
        ->assertMissing('[data-test="more-panel"]')
        ->assertNoJavaScriptErrors();
});

it('does not open the sidebar with Ctrl+B at 767 px', function () {
    $page = visit(route('dashboard', $this->team))->resize(MOBILE_WIDTH, 900);

    $page->withKeyDown('Control', fn ($page) => pressKey($page, 'b'));

    $page->assertMissing('[data-sidebar="sidebar"]')
        ->assertNoJavaScriptErrors();
});

it('shows the breadcrumb header at 767 px only with two or more breadcrumbs', function () {
    visit(route('dashboard', $this->team))
        ->resize(MOBILE_WIDTH, 900)
        ->assertMissing('[data-test="breadcrumb-header"]')
        ->assertNoJavaScriptErrors();

    visit(route('teams.edit', $this->team))
        ->resize(MOBILE_WIDTH, 900)
        ->assertVisible('[data-test="breadcrumb-header"]')
        ->assertSeeIn('[data-test="breadcrumb-header"]', 'Familia')
        ->assertNoJavaScriptErrors();
});

it('keeps the end of the page above the bottom bar', function () {
    visit(route('profile.edit'))
        ->resize(MOBILE_WIDTH, 500)
        ->assertScript(<<<'JS'
            () => {
                window.scrollTo(0, document.documentElement.scrollHeight);
                const content = document.querySelector('[data-slot="sidebar-inset"]').lastElementChild;
                const bar = document.querySelector('[data-test="bottom-nav"]');

                return content.getBoundingClientRect().bottom <= bar.getBoundingClientRect().top;
            }
            JS, true)
        ->assertNoJavaScriptErrors();
});
