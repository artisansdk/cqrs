<?php

namespace ArtisanSdk\CQRS\Queries;

use ArtisanSdk\Contract\Query as Contract;
use ArtisanSdk\CQRS\Dispatcher;
use ArtisanSdk\CQRS\Traits\Arguments;
use ArtisanSdk\CQRS\Traits\CQRS;
use ArtisanSdk\CQRS\Traits\Silencer;
use Illuminate\Database\Eloquent\Builder;

// @todo docblock
abstract class Query implements Contract
{
    use Arguments, CQRS, Silencer;

    /**
     * Create new instance of query.
     *
     * @return \ArtisanSdk\Contract\Query
     */
    public static function make()
    {
        return Dispatcher::make()->query(static::class);
    }

    /**
     * Get the query builder.
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function builder()
    {
        return app(Builder::class);
    }

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
     * Get the query result as paginated.
     *
     * @param int $max  results
     * @param int $page of results
     *
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function paginate($max = 25, $page = null)
    {
        return $this->builder()->paginate($max, $page);
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
}
