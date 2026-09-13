<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class VnListCacheService
{
    public function key(string $name): string
    {
        $version = Cache::rememberForever('public_lists.version', fn () => (string) Str::uuid());

        return "{$name}:{$version}";
    }

    public function clearPublicListsCache(): void
    {
        Cache::forget('public_lists.version');
        if (DB::transactionLevel() > 0) {
            DB::afterCommit(fn () => Cache::forget('public_lists.version'));
        }
    }
}
