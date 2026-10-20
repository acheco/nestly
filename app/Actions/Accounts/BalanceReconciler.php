<?php

namespace App\Actions\Accounts;

use App\DTOs\Accounts\BalanceDrift;
use App\DTOs\Accounts\ReconciliationReport;
use App\Enums\TransactionType;
use App\Models\Team;
use Illuminate\Support\Facades\DB;

final class BalanceReconciler
{
    public function check(Team $team): ReconciliationReport
    {
        $income = TransactionType::Income->value;

        $rows = DB::select(<<<'SQL'
            SELECT a.id,
               a.current_balance,
               a.initial_balance
                   + COALESCE(SUM(CASE WHEN t.type = ? THEN t.amount ELSE -t.amount END), 0) AS expected
        FROM accounts a
        LEFT JOIN transactions t ON t.account_id = a.id
        WHERE a.team_id = ?
        GROUP BY a.id, a.current_balance, a.initial_balance
        HAVING a.initial_balance
                   + COALESCE(SUM(CASE WHEN t.type = ? THEN t.amount ELSE -t.amount END), 0)
                   <> a.current_balance
       ORDER BY a.id
       SQL, [$income, $team->id, $income]);

        $drifts = array_map(
            fn (object $row) => new BalanceDrift(
                account_id: $row->id,
                expected: $row->expected,
                actual: $row->current_balance
            ), $rows
        );

        return new ReconciliationReport(drifts: $drifts);
    }
}
