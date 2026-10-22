<?php

namespace App\Console\Commands;

use App\Actions\Accounts\BalanceReconciler;
use App\DTOs\Accounts\ReconciliationReport;
use App\Models\Team;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('nestly:reconcile {--team= : Slug of a single team} {--fix : Rewrite drifted balances}')]
#[Description('Check account balances and transfer groups against the ledger (TRD §3.8.3)')]
class ReconcileBalancesCommand extends Command
{
    public function handle(BalanceReconciler $reconciler): int
    {
        $slug = $this->option('team');
        $fix = (bool) $this->option('fix');

        if ($slug !== null && ! Team::where('slug', $slug)->exists()) {
            $this->error("No existe ningún team con el slug '{$slug}'. Revisa el valor de --team."); // TODO(i18n)

            return self::FAILURE;
        }

        $unresolved = false;

        Team::query()
            ->when($slug, fn ($query, $slug) => $query->where('slug', $slug))
            ->chunkById(100, function ($teams) use ($reconciler, $fix, &$unresolved) {
                foreach ($teams as $team) {
                    $report = $reconciler->check($team);

                    if ($report->isClean()) {
                        continue;
                    }

                    $this->warn("Team {$team->slug}");
                    $this->renderReport($report);

                    if ($fix) {
                        foreach ($report->drifts as $drift) {
                            $fixed = $reconciler->fixBalance($team, $drift->account_id);

                            if ($fixed !== null) {
                                $this->info("Fixed {$fixed->account_id}: {$fixed->actual} → {$fixed->expected}");
                            }
                        }
                    }

                    $canNotBeFixed = $report->brokenGroups !== [] || $report->orphanPrivateTransactions !== [];

                    if (! $fix || $canNotBeFixed) {
                        $unresolved = true;
                    }
                }
            });

        return $unresolved ? self::FAILURE : self::SUCCESS;
    }

    private function renderReport(ReconciliationReport $report): void
    {
        if ($report->drifts !== []) {
            $this->table(['Account', 'Expected', 'Actual'], array_map(fn ($drift) => [$drift->account_id, $drift->expected, $drift->actual], $report->drifts));
        }
        if ($report->brokenGroups !== []) {
            $this->table(['Transfer group', 'Reasons'], array_map(fn ($group) => [$group->transfer_group_id, implode(', ', array_map(fn ($r) => $r->value, $group->reasons))], $report->brokenGroups));
        }
        if ($report->orphanPrivateTransactions !== []) {
            $this->table(['Transaction'], array_map(fn ($id) => [$id], $report->orphanPrivateTransactions));
        }
    }
}
