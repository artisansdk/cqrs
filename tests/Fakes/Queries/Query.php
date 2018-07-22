<?php

namespace ArtisanSdk\CQRS\Tests\Fakes\Queries;

use ArtisanSdk\CQRS\Queries\Query as Base;
use ArtisanSdk\CQRS\Tests\Fakes\Database\Connection;
use Illuminate\Database\Query\Builder;

class Query extends Base
{
    public function builder()
    {
        return new Builder(new Connection());
    }

    public function run()
    {
        return true;
    }

    public function __call($method, $arguments = [])
    {
        if ('test' === substr($method, 0, 4)) {
            $method = camel_case(str_replace('test', '', $method));

            return $this->$method(...$arguments);
        }

        throw new BadMethodCallException('Method '.$method.'() does not exist on '.__CLASS__.'.');
    }
}
