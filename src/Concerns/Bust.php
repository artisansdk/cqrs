<?php

declare(strict_types=1);

namespace ArtisanSdk\CQRS\Concerns;

use Illuminate\Support\Facades\Cache;

trait Bust
{
    /**
     * Bust a query.
     *
     * @return bool
     */
    public static function bust(): bool
    {
        $query = new static;
        $key = (string) ($query->key ?? static::class);
        $cache = Cache::driver($query->driver ?? null);
        collect($cache->get($key))->each(fn (string $subkey) => $cache->forget($subkey));

        $cache->forget($key);

        return true;
    }
}
