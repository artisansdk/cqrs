<?php

namespace ArtisanSdk\CQRS\Tests\Fakes\Commands;

use ArtisanSdk\Contract\Transactional as Contract;

class Transactional extends Command implements Contract
{
    public function test()
    {
        return true;
    }
}
