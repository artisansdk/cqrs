<?php

namespace ArtisanSdk\CQRS;

use ArtisanSdk\Contract\Invokable;
use ArtisanSdk\Contract\Query as Contract;
use ArtisanSdk\CQRS\Concerns\Arguments;
use ArtisanSdk\CQRS\Concerns\CQRS;
use ArtisanSdk\CQRS\Concerns\Silencer;
use Illuminate\Database\Query\Builder;

/**
 * Query Base Class.
 *
 * @example  $statement = Query::make($arguments)->toSql()
 *          $collection = Query::make($arguments)->builder()->where('foo', 'bar')->get()
 *          $collection = Query::make($arguments)->get()
 *          $collection = Query::make($arguments)->paginate(25)
 */
abstract class Query implements Contract
{
    use Arguments;
    use CQRS;
    use Silencer;

    /**
     * Create new instance of query.
     *
     * @param array $arguments
     *
     * @return \ArtisanSdk\Contract\Query
     */
    public static function make(array $arguments = [])
    {
        return Dispatcher::make()->query(static::class)->arguments($arguments);
    }

    /**
     * Invoke the query.
     *
     * @return mixed
     */
    public function __invoke()
    {
        return $this->run();
    }

    /**
     * Get the query builder.
     *
     * @return \Illuminate\Database\Query\Builder
     */
    abstract public function builder();

    /**
     * Convert the query builder to a SQL statement.
     *
     * @return string
     */
    public function toSql()
    {
        return $this->builder()->toSql();
    }

    /**
     * Run the query.
     *
     * @return mixed
     */
    public function run()
    {
        return $this->builder()->get();
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
        return $this->builder()
            ->paginate($max, $columns, $name, $page)
            ->appends(array_except($this->arguments(), ['page']));
    }
    
    /**
     * Get the base most runnable.
     *
     * @return \ArtisanSdk\Contract\Invokable
     */
    public function toBase(): Invokable
    {
        return $this;
    }
}
