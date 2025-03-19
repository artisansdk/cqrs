<?php

declare(strict_types=1);

namespace ArtisanSdk\CQRS\Buses;

use ArtisanSdk\Contract\{Cacheable, Invokable, Query as Contract, Runnable};
use ArtisanSdk\CQRS\Dispatcher;
use ArtisanSdk\CQRS\Events\Invalidated;
use Closure;
use Exception;
use Illuminate\Cache\CacheManager as Manager;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Support\Arr;
use ReflectionClass;
use RuntimeException;

/**
 * Cached Runnable Wrapper.
 */
class Cached implements Contract
{
    /**
     * The underlying runnable this class proxies to.
     *
     * @var Runnable
     */
    protected $runnable;

    /**
     * The runnable dispatcher.
     *
     * @var Dispatcher
     */
    protected $dispatcher;

    /**
     * The cache repository driver.
     *
     * @var Cache
     */
    protected $driver;

    /**
     * The cached status of the command.
     *
     * @var bool
     */
    protected $cached = true;

    /**
     * Inject the underlying  that this class proxies to.
     *
     * @param  Runnable  $runnable
     * @param  Dispatcher  $dispatcher
     * @param  Cache  $driver
     */
    public function __construct(Runnable $runnable, ?Dispatcher $dispatcher = null, ?Cache $driver = null)
    {
        $this->runnable = $runnable;
        $this->dispatcher = $dispatcher ?? Dispatcher::make();
        $this->driver = $driver;
    }

    /**
     * Get the base most runnable.
     *
     * @return Invokable
     */
    public function toBase(): Invokable
    {
        return $this->runnable->toBase();
    }

    /**
     * Should the query be cached?
     *
     * @return bool
     */
    public function cached(): bool
    {
        return $this->cached;
    }

    /**
     * Do cache the query.
     *
     * @return self
     */
    public function cache(): self
    {
        $this->cached = true;

        return $this;
    }

    /**
     * Don't cache the query.
     *
     * @return self
     */
    public function nocache(): self
    {
        $this->cached = false;

        return $this;
    }

    /**
     * Get or set the cache TTL.
     *
     * @param  int|null  $ttl  in seconds
     * @return int|self
     */
    public function ttl(?int $ttl = null)
    {
        $runnable = $this->toBase();

        if (is_null($ttl)) {
            return (int) ($runnable->ttl ?? 60);
        }

        $runnable->ttl = $ttl;

        return $this;
    }

    /**
     * Get or set the cache forever status.
     *
     * @param  bool|null  $forever
     * @return int|self
     */
    public function forever(?bool $forever = null)
    {
        $runnable = $this->toBase();

        if (is_null($forever)) {
            return (bool) ($runnable->forever ?? false);
        }

        $runnable->forever = $forever;

        return $this;
    }

    /**
     * Get or set the cache key.
     *
     * @param  string|null  $key
     * @return string|self
     */
    public function key(?string $key = null)
    {
        $runnable = $this->toBase();

        if (is_null($key)) {
            return $this->computeKey($runnable);
        }

        $runnable->key = $key;

        return $this;
    }

    /**
     * Get or set the cache sub key.
     *
     * @param  string|null  $subkey
     * @return string|self
     */
    public function subkey(?string $subkey = null)
    {
        $runnable = $this->toBase();

        if (is_null($subkey)) {
            return $this->computeSubkey($runnable);
        }

        $runnable->subkey = $subkey;

        return $this;
    }

    /**
     * Run the query outside the cache.
     *
     * @return mixed
     */
    public function fresh()
    {
        return $this->nocache()->__invoke();
    }

    /**
     * Run the query after invalidating the cache.
     *
     * @return mixed
     */
    public function refresh()
    {
        return $this->bust()->__invoke();
    }

    /**
     * Run the runnable and cache the response or invalidate the tags.
     *
     * @return mixed
     */
    public function run()
    {
        return $this->wrap(fn () => $this->runnable->run());
    }

    /**
     * Get the query result (alias of run).
     *
     * @return mixed
     */
    public function get()
    {
        return $this->run();
    }

    /**
     * Paginate the given query into a simple paginator.
     *
     * @param  int  $max  per page
     * @param  array  $columns  to fetch
     * @param  string  $name  of page request param
     * @param  int|null  $page  number
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function paginate($max = 25, $columns = ['*'], $name = 'page', $page = null)
    {
        return $this->wrap(fn () => $this->runnable->paginate($max, $columns, $name, $page));
    }

    /**
     * Invalidate the cache by tags.
     *
     * @return self
     */
    public function invalidate(): self
    {
        $this->dispatcher->event(new Invalidated($this->tags()));

        return $this;
    }

    /**
     * Bust the cache key.
     *
     * @return self
     */
    public function bust(): self
    {
        $driver = $this->driver();
        $key = $this->key();
        $subkey = $this->subKey();
        $index = $key.':'.$subkey;

        $keys = (array) $driver->get($key);
        Arr::forget($keys, $index);
        $driver->put($key, array_unique($keys), $this->forever() ? null : $this->ttl());

        $driver->forget($index);

        return $this;
    }

    /**
     * Wrap the closure in the caching layer before returning the response.
     *
     * @param  Closure  $callable
     * @return mixed
     */
    protected function wrap(Closure $callable)
    {
        $driver = $this->driver();
        $key = $this->key();
        $subkey = $this->subkey();
        $index = $key.':'.$subkey;

        $runnable = $this->toBase();

        if ($this->cached() && $runnable instanceof Cacheable) {
            if ($driver->has($index)) {
                return $driver->get($index);
            }
        }

        $response = $callable();

        if ($this->cached() && $runnable instanceof Cacheable) {

            $this->forever()
                ? $driver->forever($index, $response)
                : $driver->put($index, $response, $this->ttl());

            $keys = (array) $driver->get($key);
            $keys[] = $index;
            $driver->put($key, array_unique($keys), $this->forever() ? null : $this->ttl());

            return $response;
        }

        if ($this->cached()) {
            $this->invalidate();
        }

        return $response;
    }

    /**
     * Get the cache driver.
     *
     * @return Cache
     */
    protected function driver()
    {
        if (is_null($this->driver)) {
            $this->driver = (new Manager($this->dispatcher->container))->driver($this->toBase()->driver ?? null);
        }

        return $this->driver;
    }

    /**
     * Get the tags for the runnable.
     *
     * @return array
     */
    protected function tags(): array
    {
        $runnable = $this->toBase();

        $tags = (array) ($runnable->tags ?? []);

        if (empty($tags)) {
            $comment = (string) (new ReflectionClass($runnable))->getDocComment();
            preg_match('/@tags\s*([a-zA-Z0-9, ()_].*)/', $comment, $matches);
            if (empty($matches)) {
                throw new RuntimeException(sprintf('The %s class must provide @tags annotation or a $tags property.', is_string($runnable) ? $runnable : get_class($runnable)));
            }
            $tags = explode('|', preg_replace('/[^a-zA-Z0-9\-\_\:\.]+/', '|', end($matches)));
        }

        array_walk($tags, function (&$value, $key) {
            $value = strtolower(trim(preg_replace('/[^a-zA-Z0-9\-\_\:\.]+/', '_', $value), '_'));
        });

        return array_unique($tags);
    }

    /**
     * Compute the key from the runnable.
     *
     * @param  Invokable  $runnable
     * @return string
     */
    protected function computeKey(Invokable $runnable): string
    {
        return (string) ($runnable->key ?? get_class($runnable));
    }

    /**
     * Compute the subkey from the runnable.
     *
     * @param  Invokable  $runnable
     * @return string
     */
    protected function computeSubkey(Invokable $runnable): string
    {
        // Catch json issues like Infinite Recursion.
        if (! $arguments = json_encode($this->arguments())) {
            throw new Exception(json_last_error_msg());
        }

        return (string) ($runnable->subkey ?? md5(json_encode($arguments)));
    }

    /**
     * Proxy calls to the underlying runnable instance.
     *
     * @param  string  $method
     * @param  array  $arguments
     * @return mixed
     */
    public function __call($method, $arguments = [])
    {
        $response = call_user_func_array([$this->runnable, $method], $arguments);

        if ($response === $this->runnable) {
            return $this;
        }

        return $response;
    }

    /**
     * Invoke the runnable.
     *
     * @return mixed
     */
    public function __invoke()
    {
        return $this->run();
    }
}
