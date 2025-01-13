<?php

declare(strict_types=1);

namespace ArtisanSdk\CQRS\Tests\Fakes\Commands;

use ArtisanSdk\Contract\Eventable as Contract;
use ArtisanSdk\CQRS\Tests\Fakes\Events\{Fizzed, Fizzing};

class Custom extends Command implements Contract
{
    public function beforeEvent()
    {
        return Fizzing::class;
    }

    public function afterEvent()
    {
        return Fizzed::class;
    }
}
