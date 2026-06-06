<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Cache\RedisStore;
use Illuminate\Support\Facades\Cache;

class VnListCacheService
{
    /**
     * Clear all cached public VN lists pages.
     */
    public function clearPublicListsCache(): void
    {
        $cacheKeys = Cache::getStore()->getPrefix() . 'public_lists:*';

        // If Redis is used, delete matching keys efficiently
        if (app()->cache->getStore() instanceof RedisStore) {
            $redis = app()->cache->getStore()->getRedis();
            $keys = $redis->keys($cacheKeys);
            if (! empty($keys)) {
                $redis->del($keys);
            }

            return;
        }

        // Fallback for file/array cache stores
        Cache::flush();
    }
}
