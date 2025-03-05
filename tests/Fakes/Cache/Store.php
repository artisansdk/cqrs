<?php

declare(strict_types=1);

namespace ArtisanSdk\CQRS\Tests\Fakes\Cache;

use Illuminate\Contracts\Cache\Store as CacheStore;

class Store implements CacheStore
{
    public function get($key)
    {
        return 'foo';
    }

    public function many(array $keys)
    {
        return ['foo', 'bar'];
    }

    public function put($key, $value, $seconds)
    {
        return true;
    }

    public function putMany(array $values, $seconds)
    {
        return true;
    }

    public function increment($key, $value = 1)
    {
        return $value++;
    }

    public function decrement($key, $value = 1)
    {
        return $value--;
    }

    public function forever($key, $value)
    {
        return true;
    }

    public function forget($key)
    {
        return true;
    }

    public function flush()
    {
        return true;
    }

    public function getPrefix()
    {
        return 'foo';
    }
}
