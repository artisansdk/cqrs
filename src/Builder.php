<?php

declare(strict_types=1);

namespace ArtisanSdk\CQRS;

use ArtisanSdk\Contract\{Invokable, Query, Queueable, Runnable};
use ArtisanSdk\CQRS\Buses\Cached;
use ArtisanSdk\CQRS\Concerns\{Arguments, Silencer};
use ArtisanSdk\CQRS\Events\Event;
use BadMethodCallException;
use Closure;
use Illuminate\Support\{Arr, Str};
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;

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
     * @var Runnable
     */
    protected $runnable;

    /**
     * The registered string macros.
     *
     * @var array
     */
    protected static $macros = [];

    /**
     * Inject the underlying command that this class proxies to.
     *
     * @param  Runnable  $runnable
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
     * @param  string  $method
     * @param  array  $arguments
     * @return self
     */
    public function __call($method, $arguments = [])
    {
        // Dynamically handle macro calls
        if (static::hasMacro($method)) {
            $macro = static::$macros[$method];

            if ($macro instanceof Closure) {
                return call_user_func_array($macro->bindTo($this, static::class), $arguments);
            }

            return $macro(...$arguments);
        }

        // The use of head() or end() to get the first argument will return false if
        // an empty array which may conflict with false as a value for the first
        // value in a non-empty array making additional checking a necessity. While
        // not ideal, we'll just use Arr::first() from Laravel instead to handle
        // this in a more sane way so null is the default value if you accidentally
        // call $argumented->foo() without a value.
        Arr::set($this->arguments, Str::snake($method), Arr::first($arguments));

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
     * Queue a qeueable command.
     *
     * @return mixed
     */
    public function queue()
    {
        return $this->forwardToBase(__FUNCTION__, Queueable::class, new Event($this->arguments()));
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
     * @param  int  $max  per page
     * @param  array  $columns  to fetch
     * @param  string  $name  of page request param
     * @param  int|null  $page  number
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
     * @param  int|null  $ttl  in seconds
     * @return int|self
     */
    public function ttl(?int $ttl = null)
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
     * @return Runnable
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
     * @param  string  $method  to forward
     * @param  string  $class  to forward to
     * @param  array  $arguments  to forward to method on class
     * @return mixed
     *
     * @throws BadMethodCallException when runnable is not an instance of $class
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
     * @param  string  $method  to forward
     * @param  array  $arguments  to forward to method on query
     * @return mixed
     */
    protected function forwardToQuery($method, ...$arguments)
    {
        return $this->forwardToBase($method, Query::class, ...$arguments);
    }

    /**
     * Proxy calls to the cached builder.
     *
     * @param  string  $method  to foward
     * @param  array  $arguments  to forward to method on cached builder
     * @return mixed
     *
     * @throws BadMethodCallException when runnable is not an instance of $class
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

    /**
     * Mix another object into the class.
     *
     * @param  object  $mixin
     * @param  bool  $replace
     * @return void
     *
     * @throws ReflectionException
     */
    public static function mixin($mixin, bool $replace = true)
    {
        $methods = (new ReflectionClass($mixin))->getMethods(
            ReflectionMethod::IS_PUBLIC | ReflectionMethod::IS_PROTECTED
        );

        foreach ($methods as $method) {
            if ($replace || ! static::hasMacro($method->name)) {
                $method->setAccessible(true);
                static::macro($method->name, $method->invoke($mixin));
            }
        }
    }

    /**
     * Register a custom macro.
     *
     * @param  string  $name
     * @param  object|callable|null  $macro
     * @return void
     */
    public static function macro($name, $macro = null)
    {
        static::$macros[$name] = $macro ?: fn (...$arguments) => $this->forwardToBase($name, Invokable::class, ...$arguments);
    }

    /**
     * Checks if macro is registered.
     *
     * @param  string  $name
     * @return bool
     */
    public static function hasMacro($name)
    {
        return isset(static::$macros[$name]);
    }
}
