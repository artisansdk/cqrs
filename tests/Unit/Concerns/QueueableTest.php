<?php

namespace ArtisanSdk\CQRS\Tests\Unit\Concerns;

use ArtisanSdk\CQRS\Jobs\Chain;
use ArtisanSdk\CQRS\Tests\Fakes\Commands\Queueable;
use ArtisanSdk\CQRS\Tests\TestCase;

class QueueableTest extends TestCase
{
    /**
     * Test that a queuable command can be dispatched now.
     */
    public function testDispatchNow()
    {
        $command = new Queueable();
        list($job, $handler) = $command->dispatchNow();

        $this->assertEquals($command, $job, 'The command queued should be the job dispatched now.');
        $this->assertSame('run', $handler, 'The handler for the queued command should be the run method.');
    }

    /**
     * Test that a queuable command can be dispatched now.
     */
    public function testWithChain()
    {
        $command = new Queueable();
        $chain = $command->withChain([
            Queueable::class,
        ]);

        $this->assertInstanceOf(Chain::class, $chain, 'The command should allow chaining of queued jobs.');
    }
}
