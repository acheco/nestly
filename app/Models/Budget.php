<?php

namespace App\Models;

use App\Enums\BudgetPeriod;
use Database\Factories\BudgetFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $team_id
 * @property string $category_id
 * @property numeric-string $limit_amount
 * @property BudgetPeriod $period
 * @property string|null $start_date
 * @property string|null $end_date
 * @property bool $rollover
 * @property string|null $created_by
 */
#[Fillable(['team_id', 'category_id', 'limit_amount', 'period', 'start_date', 'end_date', 'rollover', 'created_by'])]
class Budget extends Model
{
    /** @use HasFactory<BudgetFactory> */
    use HasFactory, HasUuids;

    protected function casts(): array
    {
        return [
            'period' => BudgetPeriod::class,
            'limit_amount' => 'decimal:2',
            'start_date' => 'date',
            'end_date' => 'date',
            'rollover' => 'boolean',
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
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
