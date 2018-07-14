<?php

namespace ArtisanSdk\CQRS\Tests\Unit\Commands;

use ArtisanSdk\Contract\Invokable;
use ArtisanSdk\Contract\Runnable;
use ArtisanSdk\CQRS\Tests\Fakes\Commands\Command;
use ArtisanSdk\CQRS\Tests\TestCase;

class CommandTest extends TestCase
{
    /**
     * Test that a command can be invoked.
     */
    public function testCommandIsInvokable()
    {
        $command = new Command();

        $this->assertInstanceOf(Invokable::class, $command, 'A command must implement the '.Invokable::class.' interface.');
        $this->assertInstanceOf(Runnable::class, $command, 'A command must implement the '.Runnable::class.' interface.');

        $this->assertSame($command->run(), $command(), 'When a command is invoked it should run.');
    }
}
