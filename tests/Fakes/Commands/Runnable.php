<?php

namespace ArtisanSdk\CQRS\Tests\Fakes\Commands;

use ArtisanSdk\Contract\Runnable as Contract;
use ArtisanSdk\CQRS\Concerns\Arguments;
use ArtisanSdk\CQRS\Concerns\Silencer;

class Runnable implements Contract
{
    use Arguments;
    use Silencer;

    public function test(...$arguments)
    {
        if (empty($arguments)) {
            return $this->arguments();
        }

        return $arguments;
    }

    public function run()
    {
        return true;
    }

    public function __invoke()
    {
        return $this->run();
    }
}
