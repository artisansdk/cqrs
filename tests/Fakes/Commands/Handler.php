<?php

declare(strict_types=1);

namespace ArtisanSdk\CQRS\Tests\Fakes\Commands;

class Handler
{
    public $queue = 'default';

    public $connection = 'default';

    public $delay = 15;

    public function handle()
    {
        return $this->run();
    }

    public function run()
    {
        return true;
    }
}
