<?php

namespace App\Models;

use App\Enums\CategoryKind;
use Database\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $team_id
 * @property string $name
 * @property string $color
 * @property string $icon
 * @property string|null $parent_id
 * @property CategoryKind $kind
 * @property bool $archived
 */
#[Fillable(['team_id', 'name', 'color', 'icon', 'parent_id', 'kind', 'archived', 'is_system'])]
class Category extends Model
{
    /** @use HasFactory<CategoryFactory> */
    use HasFactory, HasUuids;

    protected function casts(): array
    {
        return [
            'kind' => CategoryKind::class,
            'is_system' => 'boolean',
            'archived' => 'boolean',
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
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    /**
     * @return HasMany<Category, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id');
    }

    /**
     * @return HasMany<Transaction, $this>
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * @return HasMany<Budget, $this>
     */
    public function budgets(): HasMany
    {
        return $this->hasMany(Budget::class);
    }

    /**
     * @return HasMany<RecurringExpense, $this>
     */
    public function recurringExpenses(): HasMany
    {
        return $this->hasMany(RecurringExpense::class);
    }
}
