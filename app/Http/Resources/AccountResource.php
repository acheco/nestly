<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AccountResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $canSeeBalance = $request->user()->can('viewBalance', $this->resource);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => $this->type->value,
            'visibility' => $this->visibility->value,
            'archived' => $this->archived,
            'owner' => $this->owner ? ['id' => $this->owner->id, 'name' => $this->owner->name] : null,
            'initialBalance' => $canSeeBalance ? $this->initial_balance : null,
            'currentBalance' => $canSeeBalance ? $this->current_balance : null,
            'can' => [
                'update' => $request->user()->can('update', $this->resource),
                'archive' => $request->user()->can('archive', $this->resource),
                'delete' => $request->user()->can('delete', $this->resource),
            ],
        ];
    }
}
