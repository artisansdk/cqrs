<?php

namespace ArtisanSdk\CQRS;

use ArtisanSdk\Contract\Query;
use ArtisanSdk\Contract\Runnable;
use ArtisanSdk\CQRS\Concerns\Arguments;
use ArtisanSdk\CQRS\Concerns\Silencer;
use ArtisanSdk\CQRS\Queries\Cached;
use BadMethodCallException;

/**
 * Runnable Class Builder.
 *
 * @example $command = (new Builder(new Command))->foo('bar')->toBase()
 *            $query = (new Builder(new Query))->foo('bar')->toBase()
 *           $result = (new Builder(new Command))->foo('bar')->run()
 *           $result = (new Builder(new Query))->foo('bar')->get()
 */
class Builder implements Runnable
{
    use Arguments;
    use Silencer;

    /**
     * The underlying runnable this class proxies to.
     *
     * @var \ArtisanSdk\Contract\Runnable
     */
    protected $runnable;

    /**
     * Inject the underlying command that this class proxies to.
     *
     * @param \ArtisanSdk\Contract\Runnable $runnable
     */
    public function __construct(Runnable $runnable)
    {
        $this->runnable = $runnable;
    }

    /**
     * Dynamically set the arguments.
     *
     * @example $argumented->foo('bar')->fizz('baz') // $argumented
     *          $argumented->arguments() // ['foo' => 'bar', 'fizz' => 'baz']
     *
     * @param string $method
     * @param array  $arguments
     *
     * @return self
     */
    public function __call($method, $arguments = [])
    {
        // The use of head() or end() to get the first argument will return false if
        // an empty array which may conflict with false as a value for the first
        // value in a non-empty array making additional checking a necessity. While
        // not ideal, we'll just use array_first() from Laravel instead to handle
        // this in a more sane way so null is the default value if you accidentally
        // call $argumented->foo() without a value.
        array_set($this->arguments, snake_case($method), array_first($arguments));

        return $this;
    }

    /**
     * Invoke the command.
     *
     * @return mixed
     */
    public function __invoke()
    {
        return $this->toBase()->__invoke();
    }

    /**
     * Run the command.
     *
     * @return mixed
     */
    public function run()
    {
        return $this->toBase()->run();
    }

    /**
     * Get the query result (alias of run).
     *
     * @return mixed
     */
    public function get()
    {
        return $this->forwardToQuery(__FUNCTION__);
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
        return $this->forwardToQuery(__FUNCTION__, $max, $columns, $name, $page);
    }

    /**
     * Should the query be cached?
     *
     * @return bool
     */
    public function cached(): bool
    {
        return $this->proxyToCached(__FUNCTION__);
    }

    /**
     * Do cache the query.
     *
     * @return self
     */
    public function cache(): self
    {
        return $this->proxyToCached(__FUNCTION__);
    }

    /**
     * Don't cache the query.
     *
     * @return self
     */
    public function nocache(): self
    {
        return $this->proxyToCached(__FUNCTION__);
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
        return $this->proxyToCached(__FUNCTION__, $ttl);
    }

    /**
     * Run the query outside the cache.
     *
     * @return mixed
     */
    public function fresh()
    {
        return $this->forwardToQuery(__FUNCTION__);
    }

    /**
     * Run the query after invalidating the cache.
     *
     * @return mixed
     */
    public function refresh()
    {
        return $this->forwardToQuery(__FUNCTION__);
    }

    /**
     * Invalidate the cache by tags.
     *
     * @return self
     */
    public function invalidate(): self
    {
        return $this->proxyToCached(__FUNCTION__);
    }

    /**
     * Bust the cache key.
     *
     * @return self
     */
    public function bust(): self
    {
        return $this->proxyToCached(__FUNCTION__);
    }

    /**
     * Get the query builder.
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function builder()
    {
        return $this->forwardToQuery(__FUNCTION__);
    }

    /**
     * Convert the query builder to a SQL statement.
     *
     * @return string
     */
    public function toSql()
    {
        return $this->forwardToQuery(__FUNCTION__);
    }

    /**
     * Assign the arguments to the underlying command and return it.
     *
     * @return \ArtisanSdk\Contract\Runnable
     */
    public function toBase()
    {
        if ($this->silenced()) {
            $this->runnable->silence();
        }

        return $this->runnable->arguments($this->arguments());
    }

    /**
     * Forward calls to the base command.
     *
     * @param string $method    to forward
     * @param string $class     to forward to
     * @param array  $arguments to forward to method on class
     *
     * @throws \BadMethodCallException when runnable is not an instance of $class
     *
     * @return mixed
     */
    protected function forwardToBase($method, $class, ...$arguments)
    {
        if ($this->runnable instanceof $class) {
            return $this->toBase()->$method(...$arguments);
        }

        throw new BadMethodCallException('Only call '.$method.'() on '.$class.' instances.');
    }

    /**
     * Forward calls to the query.
     *
     * @param string $method    to forward
     * @param array  $arguments to forward to method on query
     *
     * @return mixed
     */
    protected function forwardToQuery($method, ...$arguments)
    {
        return $this->forwardToBase($method, Query::class, ...$arguments);
    }

    /**
     * Proxy calls to the cached builder.
     *
     * @param string $method    to foward
     * @param array  $arguments to forward to method on cached builder
     *
     * @throws \BadMethodCallException when runnable is not an instance of $class
     *
     * @return mixed
     */
    protected function proxyToCached($method, ...$arguments)
    {
        if ($this->runnable instanceof Cached) {
            $response = $this->runnable->$method(...$arguments);

            if ($response === $this->runnable) {
                return $this;
            }

            return $response;
        }

        throw new BadMethodCallException('Only call '.$method.'() on '.Cached::class.' instances.');
    }
}
