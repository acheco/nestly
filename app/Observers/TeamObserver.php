<?php

namespace App\Observers;

use App\Models\Category;
use App\Models\Team;
use App\Support\DefaultCategories;

class TeamObserver
{
    public function created(Team $team): void
    {
        foreach (DefaultCategories::all() as $category) {
            Category::create([
                'team_id' => $team->id,
                'name' => $category['name'],
                'icon' => $category['icon'],
                'color' => $category['color'],
                'kind' => $category['kind'],
                'is_system' => true,
                'archived' => false,
            ]);
        }
    }
}
