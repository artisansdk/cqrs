<?php

namespace ArtisanSdk\CQRS;

use BadMethodCallException;
use Illuminate\Support\Arr;
use ArtisanSdk\Contract\Invokable;
use ArtisanSdk\CQRS\Concerns\CQRS;
use Illuminate\Support\Collection;
use ArtisanSdk\CQRS\Concerns\Silencer;
use Illuminate\Database\Query\Builder;
use ArtisanSdk\CQRS\Concerns\Arguments;
use ArtisanSdk\Contract\Query as Contract;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

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
     * @param array $arguments
     *
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
        if($this->hasBuilder()) {
            return $this->builder()->toSql();
        }

        throw new BadMethodCallException("You must implement 'builder' on class: " . __CLASS__);
    }

    /**
     * Run the query.
     *
     * @return mixed
     */
    public function run()
    {
        if($this->hasBuilder()) {
            return $this->builder()->get();
        }

        return;
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
        if($this->hasBuilder()) {
            return $this->builder()
                ->paginate($max, $columns, $name, $page)
                ->appends(Arr::except($this->arguments(), ['page']));
        }

        $results = $this->run();

        if(! is_array($results) && ! $results instanceof Arrayable && ! $results instanceof Collection) {
            $results = collect([$results]);
        }

        /** @var \Illuminate\Support\Collection $results */
        return (new LengthAwarePaginator($results, $results->count(), $max, $page))
            ->appends(Arr::except(func_get_args(), ['page']));

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

    /**
     * Does $this have a builder?
     *
     * @return boolean
     */
    protected function hasBuilder(): bool
    {
        return method_exists($this, 'builder');
    }
}
