<?php

namespace ArtisanSdk\CQRS\Queries;

use Illuminate\Database\Eloquent\Model;

// @todo docblock
abstract class Search extends Query
{
    /**
     * Underlying model instance.
     *
     * @var \Illuminate\Database\Eloquent\Model // @todo switch to ArtisanSdk\Model\Model
     */
    protected $model;

    /**
     * Inject the models needed by this query.
     *
     * @param \Illuminate\Database\Eloquent\Model $model // @todo switch to ArtisanSdk\Model\Model
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
        return $this->model->newQuery();
    }

    /**
     * Scope query to keyword.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string                                $argument
     * @param string                                $field
     *
     * @return self
     */
    protected function scopeKeyword($query, string $keyword = null, array $fields = [])
    {
        if ($keyword) {
            $query->where(function ($query) use ($keyword, $fields) {
                foreach ($fields as $field) {
                    $query->orWhere($field, 'LIKE', '%'.$keyword.'%');
                }
            });
        }

        return $this;
    }
}
