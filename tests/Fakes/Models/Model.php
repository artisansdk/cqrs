<?php

namespace ArtisanSdk\CQRS\Tests\Fakes\Models;

use Exception;
use Illuminate\Database\Eloquent\Model as Eloquent;
use Illuminate\Support\MessageBag;

class Model extends Eloquent
{
    public $result = true;

    public function save(array $options = [])
    {
        return $this->result;
    }

    public function throwValidationException($message)
    {
        throw new Exception($message);
    }

    public function getErrors()
    {
        return new MessageBag(['The foo field is required.']);
    }
}
