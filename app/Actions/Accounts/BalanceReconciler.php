<?php

namespace App\Actions\Accounts;

use App\DTOs\Accounts\BalanceDrift;
use App\DTOs\Accounts\BrokenTransferGroup;
use App\DTOs\Accounts\ReconciliationReport;
use App\Enums\AccountVisibility;
use App\Enums\BrokenTransferReason;
use App\Enums\TransactionType;
use App\Models\Team;
use Illuminate\Support\Facades\DB;

/**
 * @phpstan-type DriftRow object{id: string, current_balance: numeric-string, expected: numeric-string}
 * @phpstan-type TransferGroupRow object{transfer_group_id: string, legs: int|string, types: int|string, amounts: int|string, dates: int|string, all_private: bool|string}
 * @phpstan-type IdRow object{id: string}
 */
final class BalanceReconciler
{
    /**
     * Independent recomputation (TRD §3.8.3): sums and transfer-pair checks run in SQL,
     * not through the actions' code, so a bug there shows up here. Reports only.
     */
    public function check(Team $team): ReconciliationReport
    {
        return new ReconciliationReport(
            drifts: $this->driftsFor($team),
            brokenGroups: $this->brokenGroupsFor($team),
            orphanPrivateTransactions: $this->orphanPrivateTransactionsFor($team),
        );
    }

    /**
     * @return list<BalanceDrift>
     */
    private function driftsFor(Team $team): array
    {
        $income = TransactionType::Income->value;

        /** @var list<DriftRow> $rows */
        $rows = DB::select(<<<'SQL'
            SELECT a.id,
                   a.current_balance,
                   a.initial_balance
                     + COALESCE(SUM(CASE WHEN t.type = ? THEN t.amount ELSE -t.amount END), 0) AS expected
            FROM accounts a
            LEFT JOIN transactions t ON t.account_id = a.id
            WHERE a.team_id = ?
            GROUP BY a.id, a.initial_balance, a.current_balance
            HAVING a.initial_balance
                     + COALESCE(SUM(CASE WHEN t.type = ? THEN t.amount ELSE -t.amount END), 0)
                   <> a.current_balance
            ORDER BY a.id
            SQL, [$income, $team->id, $income]);

        return array_map(
            fn (object $row) => new BalanceDrift(
                account_id: $row->id,
                expected: (string) $row->expected,
                actual: (string) $row->current_balance,
            ),
            $rows,
        );
    }

    /**
     * Pair invariants of ERD §4.3.3 and §4.3.15. One group can break several at once.
     *
     * @return list<BrokenTransferGroup>
     */
    private function brokenGroupsFor(Team $team): array
    {
        $private = AccountVisibility::Private->value;

        /** @var list<TransferGroupRow> $rows */
        $rows = DB::select(<<<'SQL'
            SELECT t.transfer_group_id,
                   COUNT(*)                   AS legs,
                   COUNT(DISTINCT t.type)     AS types,
                   COUNT(DISTINCT t.amount)   AS amounts,
                   COUNT(DISTINCT t.date)     AS dates,
                   BOOL_AND(a.visibility = ?) AS all_private
            FROM transactions t
            JOIN accounts a ON a.id = t.account_id
            WHERE t.team_id = ? AND t.transfer_group_id IS NOT NULL
            GROUP BY t.transfer_group_id
            HAVING COUNT(*) <> 2
                OR COUNT(DISTINCT t.type) <> 2
                OR COUNT(DISTINCT t.amount) <> 1
                OR COUNT(DISTINCT t.date) <> 1
                OR BOOL_AND(a.visibility = ?)
            ORDER BY t.transfer_group_id
            SQL, [$private, $team->id, $private]);

        return array_map(
            fn (object $row) => new BrokenTransferGroup(
                transfer_group_id: $row->transfer_group_id,
                reasons: $this->reasonsFor($row),
            ),
            $rows,
        );
    }

    /**
     * @param  TransferGroupRow  $row
     * @return list<BrokenTransferReason>
     */
    private function reasonsFor(object $row): array
    {
        $reasons = [];

        if ((int) $row->legs !== 2) {
            $reasons[] = BrokenTransferReason::LegCount;
        }
        // Con una sola pata no hay tipos que comparar; eso ya lo cubre LegCount.
        if ((int) $row->legs >= 2 && (int) $row->types !== 2) {
            $reasons[] = BrokenTransferReason::SameType;
        }
        if ((int) $row->amounts !== 1) {
            $reasons[] = BrokenTransferReason::AmountMismatch;
        }
        if ((int) $row->dates !== 1) {
            $reasons[] = BrokenTransferReason::DateMismatch;
        }
        if ((bool) $row->all_private) {
            $reasons[] = BrokenTransferReason::PrivateToPrivate;
        }

        return $reasons;
    }

    /**
     * Private accounts accept transfer legs only (ERD §4.3.15).
     *
     * @return list<string>
     */
    private function orphanPrivateTransactionsFor(Team $team): array
    {
        /** @var list<IdRow> $rows */
        $rows = DB::select(<<<'SQL'
            SELECT t.id
            FROM transactions t
            JOIN accounts a ON a.id = t.account_id
            WHERE t.team_id = ? AND a.visibility = ? AND t.transfer_group_id IS NULL
            ORDER BY t.id
            SQL, [$team->id, AccountVisibility::Private->value]);

        return array_map(fn (object $row) => (string) $row->id, $rows);
    }
}
