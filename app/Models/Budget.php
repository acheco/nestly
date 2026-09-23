<?php

namespace App\Models;

use App\Enums\BudgetPeriod;
use App\Enums\BudgetScope;
use Database\Factories\BudgetFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['team_id', 'category_id', 'limit_amount', 'period', 'start_date', 'end_date', 'rollover', 'scope', 'owner_id'])]
class Budget extends Model
{
    /** @use HasFactory<BudgetFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'period' => BudgetPeriod::class,
            'scope' => BudgetScope::class,
            'limit_amount' => 'decimal:2',
            'start_date' => 'date',
            'end_date' => 'date',
            'rollover' => 'boolean',
        ];
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }
}
