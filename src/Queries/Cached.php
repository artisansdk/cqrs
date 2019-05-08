<?php

namespace ArtisanSdk\CQRS\Queries;

use ArtisanSdk\Contract\Cacheable;
use ArtisanSdk\Contract\Query as Contract;
use ArtisanSdk\Contract\Runnable;
use ArtisanSdk\CQRS\Dispatcher;
use ArtisanSdk\CQRS\Events\Invalidated;
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
     * The underlying Cacheable this class proxies to.
     *
     * @var \ArtisanSdk\Contract\Cacheable
     */
    protected $cacheable;

    /**
     * The cacheable dispatcher.
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
     * @param \ArtisanSdk\Contract\Runnable          $taggable
     * @param \ArtisanSdk\CQRS\Dispatcher            $dispatcher
     * @param \Illuminate\Contracts\Cache\Repository $driver
     */
    public function __construct(Runnable $taggable, Dispatcher $dispatcher = null, Cache $driver = null)
    {
        $this->taggable = $taggable;
        $this->dispatcher = $dispatcher ?? Dispatcher::make();
        $this->driver = $driver;
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
        if (is_null($ttl)) {
            return (int) $this->taggable->ttl;
        }

        $this->taggable->ttl = $ttl;

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
        return $this->invalidate()->__invoke();
    }

    /**
     * Run the cacheable and cache the response.
     *
     * @return mixed
     */
    public function run()
    {
        return $this->wrap(function () {
            return $this->taggable->run();
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
            return $this->taggable->paginate($max, $columns, $name, $page);
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

        if ($this->cached() && $this->taggable instanceof Cacheable) {
            if ($driver->has($index)) {
                return $driver->get($index);
            }
        }

        $response = $callable();

        if ($this->cached() && $this->taggable instanceof Cacheable) {
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
            $this->driver = app(Manager::class)->driver($this->taggable->driver ?? null);
        }

        return $this->driver;
    }

    /**
     * Get the tags for the cacheable.
     *
     * @return array
     */
    protected function tags(): array
    {
        $tags = (array) ($this->taggable->tags ?? []);

        if (empty($tags)) {
            $comment = (new ReflectionClass($this->taggable))->getDocComment();
            preg_match('/@tags\s*([a-zA-Z0-9, ()_].*)/', $comment, $matches);
            if (empty($matches)) {
                throw new RuntimeException('The '.$this->taggable.' class must provide @tags annotation or a $tags property.');
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
        return $this->taggable->key ?? get_class($this->taggable);
    }

    /**
     * Get the cache subkey.
     *
     * @return string
     */
    protected function subkey(): string
    {
        return md5(http_build_query($this->taggable->arguments()));
    }

    /**
     * Proxy calls to the underlying Cacheable instance.
     *
     * @param string $method
     * @param array  $arguments
     *
     * @return mixed
     */
    public function __call($method, $arguments = [])
    {
        $response = call_user_func_array([$this->taggable, $method], $arguments);

        if ($response === $this->taggable) {
            return $this;
        }

        return $response;
    }

    /**
     * Invoke the cacheable.
     *
     * @return mixed
     */
    public function __invoke()
    {
        return $this->run();
    }
}
