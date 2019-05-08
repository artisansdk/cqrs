<?php

namespace ArtisanSdk\CQRS\Tests\Unit\Traits;

use ArtisanSdk\CQRS\Jobs\Pending;
use ArtisanSdk\CQRS\Tests\Fakes\Command;
use ArtisanSdk\CQRS\Tests\Fakes\Commands\Queueable;
use ArtisanSdk\CQRS\Tests\TestCase;
use ArtisanSdk\Event\Event;

class HandleTest extends TestCase
{
    /**
     * Test that an event can be handled by a command.
     */
    public function testHandle()
    {
        $command = new Command();
        $event = new Event(['foo' => 'bar']);
        $response = $command->handle($event);

        $this->assertTrue($response, 'The command should have run when the event was handled.');
        $this->assertSame('bar', $command->argument('foo'), 'The "foo" property value of "bar" on the event should have been set as the "foo" argument on the command.');
    }

    /**
     * Test that a queueable command handles an event via a queued job.
     */
    public function testQueuable()
    {
        $command = new Queueable();
        $event = new Event(['foo' => 'bar']);
        $response = $command->handle($event);

        $this->assertInstanceOf(Pending::class, $response, 'The queueable command should have been queued as a job when the event was handled.');
    }
}
