<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Models\VnList;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class VnListService
{
    private const EDITABLE = ['name', 'description', 'is_public'];

    /** @param  array{name: string, description?: ?string, is_public?: bool}  $attributes */
    public function create(User $user, array $attributes, ?int $gameId = null): VnList
    {
        return DB::transaction(function () use ($user, $attributes, $gameId) {
            $list = $user->vnLists()->create([
                ...Arr::only($attributes, self::EDITABLE),
                'is_public' => $attributes['is_public'] ?? false,
                'is_default' => false,
                'type' => 'custom',
            ]);
            if ($gameId !== null) {
                $list->addGame($gameId);
            }

            return $list;
        });
    }

    /** @param  array{name?: string, description?: ?string, is_public?: bool}  $attributes */
    public function update(VnList $list, array $attributes): VnList
    {
        $list->update(Arr::only($attributes, self::EDITABLE));

        return $list;
    }
}
