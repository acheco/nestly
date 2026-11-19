<?php

use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\User;
use Illuminate\Routing\RouteCollection;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia;

// Spec 001, R6 (D-102): the dev showcase page exists in local and testing only. The tests use the
// literal URL because the route name does not exist in every environment.

it('renders the showcase page in testing', function () {
    // Without the Vite tags the page renders whether or not public/build has the new page.
    $this->withoutVite();

    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
    $user->forceFill(['current_team_id' => $team->id])->save();

    $this->actingAs($user)
        ->get('/dev/showcase')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->component('dev/Showcase'));
});

it('does not register the showcase route in production', function () {
    app()->detectEnvironment(fn (): string => 'production');

    // routes/web.php checks the environment when it loads, so it is loaded again into an
    // empty collection with the environment already set to production.
    $router = app('router');
    $router->setRoutes(new RouteCollection);
    require base_path('routes/web.php');

    // Names are set after a route joins the collection, so Route::has() only sees them once the
    // lookups are refreshed. Laravel does this at boot; here it has to be done by hand.
    $router->getRoutes()->refreshNameLookups();

    // 'dashboard' is a positive control: it proves the reload happened and the lookups were
    // refreshed, so the false below means something. If phase 6 renames that route, change it here.
    expect(Route::has('dashboard'))->toBeTrue()
        ->and(Route::has('dev.showcase'))->toBeFalse();
});
