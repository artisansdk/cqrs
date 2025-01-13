<?php

declare(strict_types=1);

namespace ArtisanSdk\CQRS\Tests\Fakes\Queries;

use ArtisanSdk\CQRS\Query as BaseQuery;
use ArtisanSdk\CQRS\Tests\Fakes\Database\Connection;
use Illuminate\Database\Query\Builder;

class Base extends BaseQuery
{
    public function builder()
    {
        return new Builder(new Connection);
    }
}
