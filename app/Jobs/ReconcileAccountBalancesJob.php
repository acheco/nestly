<?php

namespace App\Jobs;

use App\Actions\Accounts\BalanceReconciler;
use App\Models\Team;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ReconcileAccountBalancesJob implements ShouldQueue
{
    use Queueable;

    public function handle(BalanceReconciler $reconciler): void
    {
        Team::query()->chunkById(100, function ($teams) use ($reconciler) {
            foreach ($teams as $team) {
                $report = $reconciler->check($team);

                if ($report->isClean()) {
                    continue;
                }

                Log::warning('Balance reconciliation found issues', [
                    'team' => $team->slug,
                    'drifts' => array_map(fn ($d) => $d->account_id, $report->drifts),
                    'broken_groups' => array_map(fn ($g) => $g->transfer_group_id, $report->brokenGroups),
                    'orphan_private_transactions' => $report->orphanPrivateTransactions,
                ]);
            }
        });
    }
}
