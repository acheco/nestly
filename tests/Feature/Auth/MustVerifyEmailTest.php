<?php

use App\Enums\TeamRole;
use App\Models\Account;
use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

test('registering sends the verification email', function () {
    Notification::fake();

    $this->post(route('register.store'), [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $user = User::where('email', 'test@example.com')->firstOrFail();

    expect($user->hasVerifiedEmail())->toBeFalse();
    Notification::assertSentTo($user, VerifyEmail::class);
});

test('an unverified user cannot open team pages', function () {
    $user = User::factory()->unverified()->create();
    $team = $user->personalTeam();

    $this->actingAs($user)
        ->get(route('dashboard', ['current_team' => $team->slug]))
        ->assertRedirect(route('verification.notice'));
});

test('an unverified user cannot write team data', function () {
    $user = User::factory()->unverified()->create();
    $team = $user->personalTeam();

    $this->actingAs($user)
        ->post(route('transactions.store', ['current_team' => $team->slug]))
        ->assertRedirect(route('verification.notice'));

    expect($team->transactions()->count())->toBe(0);
});

test('an unverified user can open the profile to correct their email', function () {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)
        ->get(route('profile.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('mustVerifyEmail', true));
});

test('an unverified user can delete their account', function () {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)
        ->delete(route('profile.destroy'), ['password' => 'password'])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('home'));

    $this->assertGuest();
    expect(User::find($user->id))->toBeNull();
});

test('an unverified user still needs the correct password to delete their account', function () {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)
        ->delete(route('profile.destroy'), ['password' => 'wrong-password'])
        ->assertSessionHasErrors('password');

    expect(User::find($user->id))->not->toBeNull();
    $this->assertAuthenticatedAs($user);
});

test('the deletion blocks also apply to an unverified user', function () {
    $user = User::factory()->unverified()->create();
    $team = $user->personalTeam();
    recordExpense($team, Account::factory()->for($team)->create(), $user);

    $this->actingAs($user)
        ->delete(route('profile.destroy'), ['password' => 'password'])
        ->assertSessionHasErrors('account');

    expect(User::find($user->id))->not->toBeNull();
});

test('changing the email sends a new verification link and blocks team pages', function () {
    Notification::fake();

    $user = User::factory()->create();
    $team = $user->personalTeam();

    $this->actingAs($user)
        ->patch(route('profile.update'), ['name' => $user->name, 'email' => 'new@example.com'])
        ->assertSessionHasNoErrors();

    Notification::assertSentTo($user, VerifyEmail::class);

    $this->actingAs($user->refresh())
        ->get(route('dashboard', ['current_team' => $team->slug]))
        ->assertRedirect(route('verification.notice'));
});

test('saving the profile with the same email sends no verification link', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch(route('profile.update'), ['name' => 'New Name', 'email' => $user->email])
        ->assertSessionHasNoErrors();

    Notification::assertNothingSent();
    expect($user->refresh()->hasVerifiedEmail())->toBeTrue();
});

test('an unverified user cannot accept an invitation sent to their email', function () {
    $team = Team::factory()->create();
    $owner = teamUser($team, TeamRole::Owner);
    $user = User::factory()->unverified()->create(['email' => 'invited@example.com']);

    $invitation = TeamInvitation::factory()->create([
        'team_id' => $team->id,
        'email' => 'invited@example.com',
        'invited_by' => $owner->id,
    ]);

    $this->actingAs($user)
        ->post(route('invitations.accept', $invitation))
        ->assertRedirect(route('verification.notice'));

    expect($user->fresh()->belongsToTeam($team))->toBeFalse();
    expect($invitation->fresh()->accepted_at)->toBeNull();
});

test('an unverified user cannot decline an invitation sent to their email', function () {
    $team = Team::factory()->create();
    $owner = teamUser($team, TeamRole::Owner);
    $user = User::factory()->unverified()->create(['email' => 'invited@example.com']);

    $invitation = TeamInvitation::factory()->create([
        'team_id' => $team->id,
        'email' => 'invited@example.com',
        'invited_by' => $owner->id,
    ]);

    $this->actingAs($user)
        ->delete(route('invitations.decline', $invitation))
        ->assertRedirect(route('verification.notice'));

    $this->assertModelExists($invitation);
});

test('the fortify home path leads a verified user to their team dashboard', function () {
    $user = User::factory()->create();
    $team = $user->personalTeam();

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertRedirect(route('dashboard', ['current_team' => $team->slug]));
});

test('the fortify home path requires a verified email', function () {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertRedirect(route('verification.notice'));
});
