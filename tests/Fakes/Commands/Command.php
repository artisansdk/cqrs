<?php

namespace ArtisanSdk\CQRS\Tests\Fakes\Commands;

use ArtisanSdk\CQRS\Commands\Command as Base;

class Command extends Base
{
    public function run()
    {
        return true;
    }
}
