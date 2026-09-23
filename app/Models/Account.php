<?php

namespace App\Models;

use App\Enums\AccountType;
use App\Enums\AccountVisibility;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['team_id', 'name', 'type', 'initial_balance', 'visibility', 'owner_id'])]
class Account extends Model
{
    use HasFactory, HasUuids;

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Account $account) {
            $account->current_balance = $account->initial_balance;
        });
    }

    protected function casts(): array
    {
        return [
            'type' => AccountType::class,
            'visibility' => AccountVisibility::class,
            'initial_balance' => 'decimal:2',
            'current_balance' => 'decimal:2',
            'archived' => 'boolean',
        ];
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }
}
