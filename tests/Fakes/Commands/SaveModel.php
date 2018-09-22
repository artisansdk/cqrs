<?php

namespace ArtisanSdk\CQRS\Tests\Fakes\Commands;

use ArtisanSdk\CQRS\Commands\Command as Base;

class SaveModel extends Base
{
    public function run()
    {
        return $this->save($this->argument('model'));
    }
}
