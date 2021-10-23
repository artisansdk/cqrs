<?php

namespace ArtisanSdk\CQRS\Tests\Unit\Jobs;

use ArtisanSdk\CQRS\Jobs\Job;
use ArtisanSdk\CQRS\Jobs\Pending;
use ArtisanSdk\CQRS\Tests\Fakes\Commands\Command;
use ArtisanSdk\CQRS\Tests\TestCase;
use ArtisanSdk\CQRS\Events\Event;

class PendingTest extends TestCase
{
    /**
     * Test that a pending dispatch can be built.
     */
    public function testBuilder()
    {
        $job = new Job(new Event(), new Command());
        $chained = new Job(new Event(), new Command());
        $dispatch = (new Pending($job))
            ->onConnection('foo')
            ->onQueue('foo')
            ->allOnConnection('foo')
            ->allOnQueue('foo')
            ->delay(15)
            ->chain([$chained]);

        $this->assertInstanceOf(Pending::class, $dispatch, 'The pending dispatch should be returned by the configuration chaining.');
    }
}
