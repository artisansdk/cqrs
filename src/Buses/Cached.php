<?php

namespace ArtisanSdk\CQRS\Buses;

use ArtisanSdk\Contract\Cacheable;
use ArtisanSdk\Contract\Invokable;
use ArtisanSdk\Contract\Query as Contract;
use ArtisanSdk\Contract\Runnable;
use ArtisanSdk\CQRS\Dispatcher;
use ArtisanSdk\Event\Invalidated;
use Closure;
use Illuminate\Cache\CacheManager as Manager;
use Illuminate\Contracts\Cache\Repository as Cache;
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
     * @var \ArtisanSdk\Contract\Runnable
     */
    protected $runnable;

    /**
     * The runnable dispatcher.
     *
     * @var \ArtisanSdk\CQRS\Dispatcher
     */
    protected $dispatcher;

    /**
     * The cache repository driver.
     *
     * @var \Illuminate\Contracts\Cache\Repository
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
     * @param \ArtisanSdk\Contract\Runnable          $runnable
     * @param \ArtisanSdk\CQRS\Dispatcher            $dispatcher
     * @param \Illuminate\Contracts\Cache\Repository $driver
     */
    public function __construct(Runnable $runnable, Dispatcher $dispatcher = null, Cache $driver = null)
    {
        $this->runnable = $runnable;
        $this->dispatcher = $dispatcher ?? Dispatcher::make();
        $this->driver = $driver;
    }

    /**
     * Get the base most runnable.
     *
     * @return \ArtisanSdk\Contract\Invokable
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
     * @param int|null $ttl in seconds
     *
     * @return int|self
     */
    public function ttl(int $ttl = null)
    {
        $runnable = $this->toBase();

        if (is_null($ttl)) {
            return (int) $runnable->ttl;
        }

        $runnable->ttl = $ttl;

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
        return $this->wrap(function () {
            return $this->runnable->run();
        });
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
     * @param int      $max     per page
     * @param array    $columns to fetch
     * @param string   $name    of page request param
     * @param int|null $page    number
     *
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function paginate($max = 25, $columns = ['*'], $name = 'page', $page = null)
    {
        return $this->wrap(function () use ($max, $columns, $name, $page) {
            return $this->runnable->paginate($max, $columns, $name, $page);
        });
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

        foreach ((array) $driver->pull($this->key()) as $index) {
            $driver->forget($index);
        }

        return $this;
    }

    /**
     * Wrap the closure in the caching layer before returning the response.
     *
     * @param \Closure $callable
     *
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
            $driver->put($index, $response, $this->ttl());
            $keys = (array) $driver->get($key);
            $keys[] = $index;
            $driver->put($key, array_unique($keys), $this->ttl());

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
     * @return \Illuminate\Contracts\Cache\Repository
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
            $comment = (new ReflectionClass($runnable))->getDocComment();
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
     * Get the cache key.
     *
     * @return string
     */
    protected function key(): string
    {
        $runnable = $this->toBase();

        if( method_exists($runnable, 'key') ) {
            return $runnable->key();
        }

        return $runnable->key ?? get_class($runnable);
    }

    /**
     * Get the cache subkey.
     *
     * @return string
     */
    protected function subkey(): string
    {
        $runnable = $this->toBase();

        if( method_exists($runnable, 'subkey') ) {
            return $runnable->subkey();
        }

        return $runnable->subkey ?? md5(json_encode($this->arguments()));
    }

    /**
     * Proxy calls to the underlying runnable instance.
     *
     * @param string $method
     * @param array  $arguments
     *
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
