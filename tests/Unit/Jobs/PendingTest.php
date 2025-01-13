<?php

declare(strict_types=1);

namespace ArtisanSdk\CQRS\Tests\Unit\Jobs;

use ArtisanSdk\CQRS\Events\Event;
use ArtisanSdk\CQRS\Jobs\{Job, Pending};
use ArtisanSdk\CQRS\Tests\Fakes\Commands\Command;
use ArtisanSdk\CQRS\Tests\TestCase;

class PendingTest extends TestCase
{
    /**
     * Test that a pending dispatch can be built.
     */
    public function test_builder()
    {
        $job = new Job(new Event, new Command);
        $chained = new Job(new Event, new Command);
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
