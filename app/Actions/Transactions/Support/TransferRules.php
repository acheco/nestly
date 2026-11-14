<?php

namespace App\Actions\Transactions\Support;

use App\Enums\AccountVisibility;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Team;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

final class TransferRules
{
    public function __construct(private readonly TransactionDateRule $dateRule) {}

    public function assertValidInput(User $user, string $fromId, string $toId, CarbonInterface $date): void
    {
        if ($fromId === $toId) {
            throw ValidationException::withMessages(['from_account_id' => __('The accounts must be different.')]);
        }

        $this->dateRule->assertNotInFuture($user, $date);
    }

    public function assertAccounts(User $user, Account $from, Account $to): void
    {
        if ($from->visibility === AccountVisibility::Private && $user->id !== $from->owner_id) {
            throw new AuthorizationException(__('Only the owner can transfer from a private account.'));
        }

        /**
         *  Private accounts only move money across the team perimeter (team → private, private → team).
         *  Private → private never touches team money, so it is rejected. Checked after the 403 so a user
         *  moving money out of someone else's private account gets 403, not a hint about the destination.
         */
        if ($from->visibility === AccountVisibility::Private && $to->visibility === AccountVisibility::Private) {
            throw ValidationException::withMessages(['to_account_id' => __('Transfers between two private accounts are not allowed.')]);
        }

        if ($from->archived) {
            throw ValidationException::withMessages(['from_account_id' => __('Transfers from an archived account are not allowed.')]);
        }

        if ($to->archived) {
            throw ValidationException::withMessages(['to_account_id' => __('Transfers to an archived account are not allowed.')]);
        }
    }

    /**
     * Must run after assertAccounts(): private → private is already rejected there,
     * so "same visibility" here can only mean team → team.
     *
     * @return array{expense: ?string, income: ?string}
     */
    public function resolveCategoryIds(Team $team, Account $from, Account $to, ?string $categoryId): array
    {
        /**
         *  Optional category (ERD §4.3.17): only when the transfer crosses the team perimeter
         *  (one team account and one private account), stored only on the team-side leg.
         *  Private → private was rejected above, so "same visibility" here can only mean team → team.
         */
        $expense = null;
        $income = null;

        if ($categoryId !== null) {
            $category = $team->categories()->findOrFail($categoryId);

            if ($from->visibility === $to->visibility) {
                throw ValidationException::withMessages(['category_id' => __('A transfer between team accounts has no category.')]);
            }

            // The team-side leg is the expense when money leaves the team, the income when it enters.
            $teamLegType = $from->visibility === AccountVisibility::Team
                ? TransactionType::Expense
                : TransactionType::Income;

            if ($category->kind->value !== $teamLegType->value) {
                throw ValidationException::withMessages(['category_id' => __('The category type does not match the transfer. Select a valid category for this record.')]);
            }

            if ($teamLegType === TransactionType::Expense) {
                $expense = $category->id;
            } else {
                $income = $category->id;
            }

        }

        return [
            'expense' => $expense,
            'income' => $income,
        ];
    }
}
