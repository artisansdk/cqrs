<?php

namespace ArtisanSdk\CQRS\Traits;

use Illuminate\Database\Eloquent\Model; // @todo change to \ArtisanSdk\CQRS\Models\Model

trait Save
{
    /**
     * Save the model or throw an exception.
     *
     * @param \Illuminate\Database\Eloquent\Model $model
     *
     * @throws \ArtisanSdk\Model\Exceptions\InvalidModel
     *
     * @return \Illuminate\Database\Eloquent\Model
     */
    protected function save(Model $model)
    {
        if ( ! $model->save()) {
            $errors = [] + implode(PHP_EOL.'- ', (array) $model->getErrors()->all());
            $message = sprintf('The %s model could not be saved because the attributes were invalid: %s', get_class($model), $errors);
            $model->throwValidationException(app()->runningInConsole() ? $message : null); // @todo remove dep on app()
        }

        return $model;
    }
}
