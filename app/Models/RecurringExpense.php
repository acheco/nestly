<?php

namespace App\Models;

use App\Enums\RecurringFrequency;
use Database\Factories\RecurringExpenseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $team_id
 * @property string $account_id
 * @property string $category_id
 * @property string|null $created_by
 * @property numeric-string $amount
 * @property RecurringFrequency $frequency
 * @property int $interval
 * @property string|null $next_run_date
 * @property string|null $end_date
 * @property int $notify_days_before
 * @property bool $active
 * @property bool $auto_create_transaction
 */
#[Fillable(['team_id',
    'account_id',
    'category_id',
    'created_by',
    'name',
    'amount',
    'frequency',
    'interval',
    'next_run_date',
    'end_date',
    'notify_days_before',
    'active',
    'auto_create_transaction', ])]
class RecurringExpense extends Model
{
    /** @use HasFactory<RecurringExpenseFactory> */
    use HasFactory, HasUuids;

    protected function casts(): array
    {
        return [
            'frequency' => RecurringFrequency::class,
            'amount' => 'decimal:2',
            'next_run_date' => 'date',
            'end_date' => 'date',
            'active' => 'boolean',
            'auto_create_transaction' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * @return BelongsTo<Account, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<Transaction, $this>
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'recurring_id');
    }
}
