<?php

declare(strict_types=1);

namespace ArtisanSdk\CQRS\Concerns;

use Illuminate\Contracts\Cache\Repository as Driver;
use Illuminate\Support\Facades\Cache;

trait Bust
{
    /**
     * Get the cache driver for the query.
     *
     * @return Driver
     */
    public static function driver(): Driver
    {
        return Cache::driver();
    }

    /**
     * Get the primary key of the query.
     *
     * @return string
     */
    public static function key(): string
    {
        return self::class;
    }

    /**
     * Bust a query.
     *
     * @return bool
     */
    public static function bust(): bool
    {
        $driver = self::driver();
        $key = self::key();

        collect($driver->get($key))->each(fn (string $subkey) => $driver->forget($subkey));

        $driver->forget($key);

        return true;
    }
}
