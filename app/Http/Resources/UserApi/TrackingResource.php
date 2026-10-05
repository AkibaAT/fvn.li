<?php

declare(strict_types=1);

namespace App\Http\Resources\UserApi;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TrackingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'game_id' => $this->game_id,
            'game' => (new GameResource($this->game))->toArray($request),
            'game_version_id' => $this->game_version_id,
            'status' => $this->status,
            'started_at' => $this->started_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'personal_notes' => $this->personal_notes,
            'is_receiving_updates' => $this->is_receiving_updates,
        ];
    }
}
