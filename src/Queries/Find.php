<?php

namespace ArtisanSdk\CQRS\Queries;

use ArtisanSdk\Value\UUID;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * Find Model Query.
 *
 * @example Find::make()->uuid(1)->get()
 *          Find::make()->uuid('28bb0668-76ca-4e23-979d-55969181bce0')->get()
 *          Find::make()->uuid(new UUID)->get()
 *          Find::make()->uuid(new Model)->get()
 *
 * @param int|string|\ArtisanSdk\Value\UUID|\Illuminate\Database\Eloquent\Model $uuid
 *
 * @return \Illuminate\Database\Eloquent\Model
 */
abstract class Find extends Query
{
    /**
     * Underlying model instance.
     *
     * @var \Illuminate\Database\Eloquent\Model
     */
    protected $model;

    /**
     * Inject the models needed by this query.
     *
     * @param \Illuminate\Database\Eloquent\Model $model // @todo switch to \ArtisanSdk\Model\Model
     */
    public function __construct(Model $model)
    {
        $this->model = $model;
    }

    /**
     * Get the query builder.
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function builder()
    {
        return $this->model->uuid($this->argument('uuid'));
    }

    /**
     * Run the query.
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     * @throws \InvalidArgumentException
     *
     * @return \Illuminate\Database\Eloquent\Model
     */
    public function run()
    {
        $uuid = $this->argument('uuid');

        if ($uuid instanceof $this->model) {
            return $uuid;
        }

        if (is_numeric($uuid)) {
            return $this->model->findOrFail($uuid);
        }

        if (is_string($uuid) || $uuid instanceof UUID) {
            return $this->builder()->firstOrFail();
        }

        throw new InvalidArgumentException('The supplied UUID argument is not valid.');
    }
}
