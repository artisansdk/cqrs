<?php

declare(strict_types=1);

namespace ArtisanSdk\CQRS\Contracts;

use Illuminate\Contracts\Cache\Repository as Driver;

interface Bustable
{
    /**
     * Get the cache driver for the query.
     *
     * @return Driver
     */
    public static function driver(): Driver;

    /**
     * Get the primary key of the query.
     *
     * @return string
     */
    public static function key(): string;

    /**
     * Bust a query.
     *
     * @return bool
     */
    public static function bust(): bool;
}
