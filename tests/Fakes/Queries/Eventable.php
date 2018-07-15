<?php

namespace ArtisanSdk\CQRS\Tests\Fakes\Queries;

use ArtisanSdk\Contract\Eventable as Contract;

class Eventable extends Query implements Contract
{
    public function test()
    {
        return true;
    }
}
