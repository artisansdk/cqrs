<?php

declare(strict_types=1);

namespace ArtisanSdk\CQRS\Concerns;

use Illuminate\Database\Eloquent\Model;

 // @todo switch to \ArtisanSdk\Models\Model

trait Save
{
    /**
     * Save the model or throw an exception.
     *
     * @param  Model  $model
     * @return Model
     *
     * @throws \ArtisanSdk\Model\Exceptions\InvalidModel
     */
    protected function save(Model $model)
    {
        if (! $model->save()) {
            if (php_sapi_name() === 'cli' || php_sapi_name() === 'phpdbg') {
                $errors = implode(PHP_EOL.'- ', (array) $model->getErrors()->all());
                $message = sprintf(
                    'The %s model could not be saved because the attributes were invalid:%s%s%sThe attributes for the model are:%s%s',
                    get_class($model),
                    PHP_EOL,
                    $errors,
                    PHP_EOL,
                    PHP_EOL,
                    $model->toJson(JSON_PRETTY_PRINT)
                );
            }

            return $model->throwValidationException($message ?? null);
        }

        return $model;
    }
}
