<?php

namespace ArtisanSdk\CQRS\Tests\Fakes\Commands;

use ArtisanSdk\CQRS\Command as Base;
use BadMethodCallException;
use Illuminate\Support\Str;

class Command extends Base
{
    public function run()
    {
        return true;
    }

    public function __call($method, $arguments = [])
    {
        if ('test' === substr($method, 0, 4)) {
            $method = Str::camel(str_replace('test', '', $method));

            return $this->$method(...$arguments);
        }

        throw new BadMethodCallException('Method '.$method.'() does not exist on '.__CLASS__.'.');
    }
}
