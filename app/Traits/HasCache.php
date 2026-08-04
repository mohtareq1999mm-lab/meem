<?php

namespace App\Traits;

use Illuminate\Support\Facades\Cache;

trait HasCache
{
    /**
     * Get data from cache or execute the callback and cache the result.
     */
    protected function remember(
        string $tag,
        string $key,
        $data,
        $ttl = null,
    ): mixed {
        $ttl ??= now()->addHours(4);

        return Cache::tags([$tag])->remember($key, $ttl, fn() => $data);
    }

    /**
     * Remove one or more cache keys.
     */
    protected function forget(string|array $keys): void
    {
        foreach ((array) $keys as $key) {
            Cache::forget($key);
        }
    }

    /**
     * Clear all cache.
     */
    protected function flush(): bool
    {
        return Cache::flush();
    }

    protected function flushTag(string $tag): bool
    {
        return Cache::tags([$tag])->flush();
    }
}