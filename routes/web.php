<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Teams\TeamInvitationController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\TransferController;
use App\Http\Middleware\EnsureTeamMembership;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

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
        Route::delete('transfers/{transfer_group_id}', [TransferController::class, 'destroy'])
            ->whereUuid('transfer_group_id')
            ->name('transfers.destroy');
    });

Route::middleware(['auth'])->group(function () {
    Route::post('invitations/{invitation}/accept', [TeamInvitationController::class, 'accept'])->name('invitations.accept');
    Route::delete('invitations/{invitation}', [TeamInvitationController::class, 'decline'])->name('invitations.decline');
});

require __DIR__.'/settings.php';
