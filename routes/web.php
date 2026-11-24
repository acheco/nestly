<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Teams\TeamInvitationController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\TransferController;
use App\Http\Middleware\EnsureTeamMembership;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Spec 001, R11: "/" is not a page. Guests go to the login; signed-in users to their home team.
Route::get('/', function (Request $request) {
    $user = $request->user();

    return $user === null
        ? to_route('login')
        : to_route('dashboard', ['current_team' => $user->homeTeam()->slug]);
})->name('home');

Route::prefix('{current_team}')
    ->middleware(['auth', 'verified', EnsureTeamMembership::class])
    ->group(function () {
        Route::get('dashboard', DashboardController::class)->name('dashboard');

        Route::post('transactions', [TransactionController::class, 'store'])->name('transactions.store');
        Route::patch('transactions/{transaction}', [TransactionController::class, 'update'])
            ->scopeBindings()
            ->name('transactions.update');
        Route::delete('transactions/{transaction}', [TransactionController::class, 'destroy'])
            ->scopeBindings()
            ->name('transactions.destroy');

        Route::post('transfers', [TransferController::class, 'store'])->name('transfers.store');

        Route::patch('transfers/{transfer_group_id}', [TransferController::class, 'update'])
            ->whereUuid('transfer_group_id')
            ->name('transfers.update');

        Route::delete('transfers/{transfer_group_id}', [TransferController::class, 'destroy'])
            ->whereUuid('transfer_group_id')
            ->name('transfers.destroy');

        Route::get('accounts', [AccountController::class, 'index'])->name('accounts.index');
    });

Route::middleware(['auth', 'verified'])->group(function () {
    // Fortify's fallback redirect after email verification steps has no team in its path.
    Route::get('dashboard', function (Request $request) {
        return to_route('dashboard', ['current_team' => $request->user()->homeTeam()->slug]);
    });

    Route::post('invitations/{invitation}/accept', [TeamInvitationController::class, 'accept'])->name('invitations.accept');
    Route::delete('invitations/{invitation}', [TeamInvitationController::class, 'decline'])->name('invitations.decline');
});

// Spec 001, R6 (D-102): the dev showcase exists only in local and testing. Checked when this
// file loads, so in production and any other environment the route is never registered.
if (app()->environment(['local', 'testing'])) {
    Route::inertia('dev/showcase', 'dev/Showcase')
        ->middleware(['auth', 'verified'])
        ->name('dev.showcase');
}

require __DIR__.'/settings.php';
