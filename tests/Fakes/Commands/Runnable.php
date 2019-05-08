<?php

namespace ArtisanSdk\CQRS\Tests\Fakes\Commands;

use ArtisanSdk\Contract\Runnable as Contract;
use ArtisanSdk\CQRS\Traits\Arguments;
use ArtisanSdk\CQRS\Traits\Silencer;

class Runnable implements Contract
{
    use Arguments;
    use Silencer;

    public function run()
    {
        return true;
    }

    public function __invoke()
    {
        return $this->run();
    }
}
