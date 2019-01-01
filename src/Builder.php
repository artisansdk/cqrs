<?php

namespace ArtisanSdk\CQRS;

use ArtisanSdk\Contract\Query;
use ArtisanSdk\Contract\Runnable;
use ArtisanSdk\CQRS\Traits\Arguments;
use ArtisanSdk\CQRS\Traits\Silencer;
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
    use Arguments, Silencer;

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
        array_set($this->arguments, $method, head($arguments));

        return $this;
    }

    /**
     * Invoke the command.
     *
     * @return mixed
     */
    public function __invoke()
    {
        $runnable = $this->toBase();

        return $runnable();
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
     * @throws \BadMethodCallException when command is not an instance of $class
     *
     * @return mixed
     */
    protected function forward($method, $class, ...$arguments)
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
        return $this->forward($method, Query::class, ...$arguments);
    }
}
