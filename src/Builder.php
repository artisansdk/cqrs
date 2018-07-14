<?php

namespace ArtisanSdk\CQRS;

use ArtisanSdk\Contract\Query;
use ArtisanSdk\Contract\Runnable;
use ArtisanSdk\CQRS\Traits\Arguments;
use ArtisanSdk\CQRS\Traits\Silencer;
use BadMethodCallException;

class Builder implements Runnable
{
    use Arguments, Silencer;

    /**
     * The underlying command this class proxies to.
     *
     * @var \ArtisanSdk\Contract\Runnable
     */
    protected $command;

    /**
     * Inject the underlying command that this class proxies to.
     *
     * @param \ArtisanSdk\Contract\Runnable $command
     */
    public function __construct(Runnable $command)
    {
        $this->command = $command;
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
        $command = $this->toBase();

        return $comand();
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
     * Get the query result as paginated.
     *
     * @param int $max  results
     * @param int $page of results
     *
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function paginate($max = 25, $page = null)
    {
        return $this->forwardToQuery(__FUNCTION__, [$max, $page]);
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
            $this->command->silence();
        }

        return $this->command->arguments($this->arguments());
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
    protected function forward($method, $class, $arguments = null)
    {
        if ($this->command instanceof $class) {
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
    protected function forwardToQuery($method, $arguments = null)
    {
        return $this->forward($method, Query::class, $arguments);
    }
}
