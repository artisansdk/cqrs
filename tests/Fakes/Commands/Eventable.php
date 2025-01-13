<?php

declare(strict_types=1);

namespace ArtisanSdk\CQRS\Tests\Fakes\Commands;

use ArtisanSdk\Contract\Eventable as Contract;

class Eventable extends Command implements Contract
{
    public function test()
    {
        return true;
    }
}
