<?php

namespace App\Models;

use App\Enums\RecurringFrequency;
use Database\Factories\RecurringExpenseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
    use HasFactory;

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

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class.'recurring_id');
    }
}
