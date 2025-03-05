<?php

declare(strict_types=1);

namespace ArtisanSdk\CQRS\Tests\Fakes\Queries;

use ArtisanSdk\CQRS\Query as BaseQuery;

class Builderless extends BaseQuery
{
    public function run()
    {
        return ['foo', 'bar', 'baz'];
    }
}
