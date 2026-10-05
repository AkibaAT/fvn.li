<?php

declare(strict_types=1);

namespace App\Http\Resources\UserApi;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ListEntryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'list_id' => $this->vn_list_id,
            'game_id' => $this->game_id,
            'sort_order' => $this->sort_order,
            'private_notes' => $this->private_notes,
            'game' => $this->game && $this->game->is_visible ? (new GameResource($this->game))->toArray($request) : null,
        ];
    }
}
