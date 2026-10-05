<?php

declare(strict_types=1);

namespace App\Http\Resources\UserApi;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GameResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'platform' => $this->platform,
            'itch_id' => $this->itch_id,
            'steam_app_id' => $this->steam_app_id,
            'urls' => array_intersect_key($this->url ?? [], array_flip(['itch_io', 'steam', 'other'])),
            'status' => $this->status,
            'is_nsfw' => $this->is_nsfw,
            'is_paid' => $this->is_paid,
            'has_demo' => $this->has_demo,
            'fvn_url' => route('games.show', $this->slug),
        ];
    }
}
