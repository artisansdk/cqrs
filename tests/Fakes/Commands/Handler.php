<?php

namespace ArtisanSdk\CQRS\Tests\Fakes\Commands;

class Handler
{
    public function handle()
    {
        return $this->run();
    }

    public function run()
    {
        return true;
    }
}
