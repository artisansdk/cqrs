<?php

namespace ArtisanSdk\CQRS\Tests\Unit\Traits;

use ArtisanSdk\CQRS\Tests\Fakes\Commands\SaveModel;
use ArtisanSdk\CQRS\Tests\Fakes\Models\Model;
use ArtisanSdk\CQRS\Tests\TestCase;
use Exception;

class SaveTest extends TestCase
{
    /**
     * Test that save fails.
     */
    public function testFailure()
    {
        $model = new Model();
        $model->result = false;
        try {
            SaveModel::make()->model($model)->run();
        } catch (Exception $exception) {
            $this->assertStringStartsWith('The '.get_class($model).' model could not be saved because the attributes were invalid', $exception->getMessage());

            return;
        }

        $this->fail('An exception should have been thrown by the save() method.');
    }

    /**
     * Test that save succeeds.
     */
    public function testSuccess()
    {
        $model = new Model();
        $model->result = true;
        $result = SaveModel::make()->model($model)->run();

        $this->assertSame($model, $result, 'The result of the command should be the model.');
        $this->assertFalse($model->exists, 'Saving the model should fail to persist the model so the model should not exist.');
    }
}
