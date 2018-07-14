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
    public function testIsInvokable()
    {
        $command = new Command();

        $this->assertInstanceOf(Invokable::class, $command, 'A command must implement the '.Invokable::class.' interface.');
        $this->assertInstanceOf(Runnable::class, $command, 'A command must implement the '.Runnable::class.' interface.');
        $this->assertSame($command->run(), $command(), 'When a command is invoked it should run.');
    }

    /**
     * Test that a command can be silenced.
     */
    public function testCanBeSilenced()
    {
        $command = new Command();

        $this->assertFalse($command->silenced(), 'The command should not be silent by default.');
        $this->assertSame($command, $command->silence(), 'When a command is silenced it should return self.');
        $this->assertTrue($command->silenced(), 'The command should be silent when silenced.');
    }

    /**
     * Test that a command can be ran silently.
     */
    public function testSilently()
    {
        $command = new Command();

        $this->assertFalse($command->silenced(), 'The command should not be silent by default.');
        $this->assertSame($command->silently(), $command->run(), 'When a command is ran silently it should still run.');
        $this->assertTrue($command->silenced(), 'The command should be silenced when called silently.');
    }
}
