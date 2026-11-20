<?php

use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\User;

// Spec 001, R9 (D-096): the menu links only to pages that exist. From md (768 px) the sidebar
// shows Inicio and none of the starter kit's links to its repository or documentation.

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->team = Team::factory()->create(['name' => 'Familia']);
    $this->team->members()->attach($this->user, ['role' => TeamRole::Owner->value]);
    $this->user->forceFill(['current_team_id' => $this->team->id])->save();

    $this->actingAs($this->user);
});

it('shows Inicio in the sidebar and no starter kit links at 768 px', function () {
    visit(route('dashboard', $this->team))
        ->resize(768, 900)
        ->assertSeeIn('[data-sidebar="sidebar"]', 'Inicio')
        ->assertDontSee('Repository')
        ->assertDontSee('Documentation')
        ->assertNotPresent('a[href*="github.com/laravel/vue-starter-kit"]')
        ->assertNotPresent('a[href*="laravel.com/docs/starter-kits"]')
        ->assertNoJavaScriptErrors();
});
