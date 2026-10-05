<?php

declare(strict_types=1);

namespace App\Http\Resources\UserApi;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'type' => $this->type,
            'is_default' => $this->is_default,
            'is_public' => $this->is_public,
            'entries_count' => $this->entries_count,
        ];
    }
}
