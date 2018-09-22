<?php

namespace ArtisanSdk\CQRS\Traits;

use Illuminate\Database\Eloquent\Model; // @todo switch to \ArtisanSdk\Models\Model

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
            if ('cli' === php_sapi_name() || 'phpdbg' === php_sapi_name()) {
                $errors = implode(PHP_EOL.'- ', (array) $model->getErrors()->all());
                $message = sprintf('The %s model could not be saved because the attributes were invalid: %s', get_class($model), $errors);
            }

            return $model->throwValidationException($message);
        }

        return $model;
    }
}
