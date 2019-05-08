<?php

namespace ArtisanSdk\CQRS\Tests\Unit\Buses;

use ArtisanSdk\Contract\Invokable;
use ArtisanSdk\Contract\Runnable;
use ArtisanSdk\Contract\Transactional as Contract;
use ArtisanSdk\CQRS\Buses\Transaction;
use ArtisanSdk\CQRS\Dispatcher;
use ArtisanSdk\CQRS\Tests\Fakes\Commands\Eventable;
use ArtisanSdk\CQRS\Tests\Fakes\Commands\Exceptional;
use ArtisanSdk\CQRS\Tests\Fakes\Commands\Transactional;
use ArtisanSdk\CQRS\Tests\Fakes\Database\Connection;
use ArtisanSdk\CQRS\Tests\Fakes\Events\Dispatcher as Events;
use ArtisanSdk\CQRS\Tests\TestCase;
use Exception;
use Illuminate\Contracts\Events\Dispatcher as EventsInterface;

class TransactionTest extends TestCase
{
    /**
     * Test that a transaction can be invoked.
     */
    public function testIsInvokable()
    {
        $command = new Transactional();
        $transaction = new Transaction($command, new Dispatcher($this->app), new Connection());

        $this->assertInstanceOf(Contract::class, $command, 'A command that should be run in a transaction must implement the '.Contract::class.' interface.');
        $this->assertInstanceOf(Invokable::class, $transaction, 'A transaction must implement the '.Invokable::class.' interface.');
        $this->assertInstanceOf(Runnable::class, $transaction, 'A transaction must implement the '.Runnable::class.' interface.');
        $this->assertSame($transaction->run(), $transaction(), 'When a transaction is invoked it should run the transaction.');
        $this->assertSame($transaction->run(), $command(), 'When a transaction is invoked it should run the command.');
    }

    /**
     * Test that a transaction proxies responses.
     */
    public function testResponseIsProxied()
    {
        $transaction = new Transaction(new Transactional(), new Dispatcher($this->app), new Connection());

        $this->assertEmpty($transaction->arguments(), 'When the proxied method return something other than the command the proxy should return the response.');
        $this->assertSame($transaction, $transaction->arguments(['foo' => 'bar']), 'When a proxied method is for a fluent method then the proxy should be returned instead.');
        $this->assertSame('bar', $transaction->argument('foo'), 'When the proxied method return something other than the command the proxy should return the response.');
        $this->assertTrue($transaction->test(), 'When the proxied method return something other than the command the proxy should return the response.');
    }

    /**
     * Test that a transactional command is silent when the command is silenced.
     */
    public function testSilentWhenSilenced()
    {
        $dispatcher = new Events();
        $this->app->singleton(EventsInterface::class, function () use ($dispatcher) {
            return $dispatcher;
        });
        $transaction = new Transaction(new Eventable(), new Dispatcher($this->app), new Connection());
        $response = $transaction->silently();
        $events = $dispatcher->events;

        $this->assertCount(0, $events, 'The silenced evented command should fire no events.');
        $this->assertTrue($response, 'The silenced command response should have been returned.');
    }

    /**
     * Test that a transaction is committed when the command is not aborted.
     */
    public function testCommitWhenNotAborted()
    {
        $database = new Connection();
        $command = new Transaction(new Transactional(), new Dispatcher($this->app), $database);
        $response = $command->run();

        $this->assertSame(0, $database->transactionLevel(), 'The transaction should have been committed.');
        $this->assertSame(1, $database->commits, 'The transaction should have been committed.');
        $this->assertSame(0, $database->rollbacks, 'The transaction should not have been rolled back.');
        $this->assertTrue($response, 'The command response should have been returned.');
    }

    /**
     * Test that a transaction rollsback when the command is aborted.
     */
    public function testRollbackWhenAborted()
    {
        $database = new Connection();
        $command = new Transaction(new Transactional(), new Dispatcher($this->app), $database);
        $response = $command->abort()->run();

        $this->assertSame(0, $database->transactionLevel(), 'The transaction should have been rolled back.');
        $this->assertSame(0, $database->commits, 'The transaction should not have been committed.');
        $this->assertSame(1, $database->rollbacks, 'The transaction should have been rolled back.');
        $this->assertTrue($response, 'The command response should have been returned.');
    }

    /**
     * Test that a transaction rollsback when command throws an exception.
     */
    public function testRollbackOnException()
    {
        $database = new Connection();
        $command = new Transaction(new Exceptional(), new Dispatcher($this->app), $database);

        try {
            $command->run();
        } catch (Exception $exception) {
            $this->assertSame(0, $database->transactionLevel(), 'The transaction should have been rolled back.');
            $this->assertSame(0, $database->commits, 'The transaction should not have been committed.');
            $this->assertSame(1, $database->rollbacks, 'The transaction should have been rolled back.');
            $this->assertSame('foo', $exception->getMessage(), 'The exception thrown in the command should be rethrown.');

            return;
        }

        $this->fail('An exception should have been rethrown by transaction wrapper.');
    }
}
