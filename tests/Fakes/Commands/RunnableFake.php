<?php

namespace ArtisanSdk\CQRS\Tests\Fakes\Commands;

use ArtisanSdk\Contract\Runnable as Contract;

class Runnable implements Contract
{
    public function run()
    {
        return true;
    }

    public function __invoke()
    {
        return $this->run();
    }
}
