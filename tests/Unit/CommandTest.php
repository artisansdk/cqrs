<?php

declare(strict_types=1);

namespace ArtisanSdk\CQRS\Tests\Unit;

use ArtisanSdk\Contract\{Command as CommandInterface, Invokable, Runnable};
use ArtisanSdk\CQRS\Builder;
use ArtisanSdk\CQRS\Jobs\Chain;
use ArtisanSdk\CQRS\Tests\Fakes\Commands\{Command, Queueable};
use ArtisanSdk\CQRS\Tests\TestCase;

class CommandTest extends TestCase
{
    /**
     * Test that a command can be made.
     */
    public function test_factory_make()
    {
        $command = Command::make();

        $this->assertInstanceOf(Builder::class, $command, 'When a command is made it should run through the dispatcher and return as a builder.');
        $this->assertInstanceOf(CommandInterface::class, $command->toBase(), 'A command must implement the '.CommandInterface::class.' interface.');
        $this->assertInstanceOf(Command::class, $command->toBase(), 'When a command is made it should return a factory instance of itself.');
        $this->assertEmpty($command->arguments(), 'When a command is made without arguments then the arguments should be an empty array.');

        $arguments = ['foo' => 'bar'];
        $command = Command::make($arguments);
        $this->assertSame($arguments, $command->arguments(), 'When a command is made with arguments then the arguments should be assigned to the returned command.');
    }

    /**
     * Test that a command can be invoked.
     */
    public function test_is_invokable()
    {
        $command = new Command;

        $this->assertInstanceOf(Invokable::class, $command, 'A command must implement the '.Invokable::class.' interface.');
        $this->assertInstanceOf(Runnable::class, $command, 'A command must implement the '.Runnable::class.' interface.');
        $this->assertSame($command->run(), $command(), 'When a command is invoked it should run.');
    }

    /**
     * Test that a command can be silenced.
     */
    public function test_can_be_silenced()
    {
        $command = new Command;

        $this->assertFalse($command->silenced(), 'The command should not be silent by default.');
        $this->assertSame($command, $command->silence(), 'When a command is silenced it should return self.');
        $this->assertTrue($command->silenced(), 'The command should be silent when silenced.');
    }

    /**
     * Test that a command can be ran silently.
     */
    public function test_silently()
    {
        $command = new Command;

        $this->assertFalse($command->silenced(), 'The command should not be silent by default.');
        $this->assertSame($command->silently(), $command->run(), 'When a command is ran silently it should still run.');
        $this->assertTrue($command->silenced(), 'The command should be silenced when called silently.');
    }

    /**
     * Test that a command can be aborted.
     */
    public function test_abortable()
    {
        $command = new Command;

        $this->assertFalse($command->aborted(), 'The command should not be aborted by default.');
        $this->assertSame($command, $command->abort(), 'When a command is aborted it should return itself.');
        $this->assertTrue($command->aborted(), 'An aborted command should report that it is aborted.');
    }

    /**
     * Test that a queuable command can be dispatched now.
     */
    public function test_dispatch_now()
    {
        $command = new Queueable;
        [$job, $handler] = $command->dispatchNow();

        $this->assertEquals($command, $job, 'The command queued should be the job dispatched now.');
        $this->assertSame('run', $handler, 'The handler for the queued command should be the run method.');
    }

    /**
     * Test that a queuable command can be dispatched now.
     */
    public function test_with_chain()
    {
        $command = new Queueable;
        $chain = $command->withChain([
            Queueable::class,
        ]);

        $this->assertInstanceOf(Chain::class, $chain, 'The command should allow chaining of queued jobs.');
    }
}
