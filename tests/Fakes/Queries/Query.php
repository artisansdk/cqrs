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
}
