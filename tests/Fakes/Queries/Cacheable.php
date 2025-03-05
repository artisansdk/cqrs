<?php

declare(strict_types=1);

namespace ArtisanSdk\CQRS\Tests\Fakes\Queries;

use ArtisanSdk\Contract\Cacheable as Contract;

class Cacheable extends Query implements Contract
{
    public $ttl = 0;

    public $forever = false;

    public function test()
    {
        return true;
    }
}
