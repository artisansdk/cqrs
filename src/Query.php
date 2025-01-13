<?php

declare(strict_types=1);

namespace ArtisanSdk\CQRS;

use ArtisanSdk\Contract\{Invokable, Query as Contract};
use ArtisanSdk\CQRS\Concerns\{Arguments, CQRS, Silencer};
use BadMethodCallException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\{Arr, Collection};

/**
 * Query Base Class.
 *
 * If you use this class to get data from an ORM, like Laravel's Eloquent, you will need to supply a
 * 'builder' method on your query that returns '\Illuminate\Database\Query\Builder' or the
 * equivalent for your ORM. All other data can be returned from the 'run' method directly.
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
     * @param  array  $arguments
     * @return \ArtisanSdk\CQRS\Builder<TContract>
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
     * Convert the query builder to a SQL statement.
     *
     * @return string
     */
    public function toSql()
    {
        if ($this->hasBuilder()) {
            return $this->builder()->toSql();
        }

        throw new BadMethodCallException("You must implement 'builder' on class: ".__CLASS__);
    }

    /**
     * Run the query.
     *
     * @return mixed
     */
    public function run()
    {
        if ($this->hasBuilder()) {
            return $this->builder()->get();
        }

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
     * @return LengthAwarePaginator
     */
    public function paginate($max = 25, $columns = ['*'], $name = 'page', $page = null)
    {
        if ($this->hasBuilder()) {
            return $this->builder()
                ->paginate($max, $columns, $name, $page)
                ->appends(Arr::except($this->arguments(), ['page']));
        }

        $results = $this->run();

        if (! is_array($results) && ! $results instanceof Arrayable && ! $results instanceof Collection) {
            $results = collect([$results]);
        }

        /** @var Collection $results */
        return (new LengthAwarePaginator($results, $results->count(), $max, $page))
            ->appends(Arr::except(func_get_args(), ['page']));

    }

    /**
     * Get the base most runnable.
     *
     * @return Invokable
     */
    public function toBase(): Invokable
    {
        return $this;
    }

    /**
     * Does $this have a builder?
     *
     * @return bool
     */
    protected function hasBuilder(): bool
    {
        return method_exists($this, 'builder');
    }
}
