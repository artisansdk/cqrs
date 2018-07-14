<?php

namespace ArtisanSdk\CQRS\Tests\Unit\Commands;

use ArtisanSdk\Contract\Invokable;
use ArtisanSdk\Contract\Runnable;
use ArtisanSdk\Contract\Transactional as Contract;
use ArtisanSdk\CQRS\Commands\Transaction;
use ArtisanSdk\CQRS\Tests\Fakes\Commands\Transactional;
use ArtisanSdk\CQRS\Tests\TestCase;

class TransactionTest extends TestCase
{
    /**
     * Test that a transaction can be invoked.
     */
    public function testTransactionIsInvokable()
    {
        $command = new Transactional();
        $transaction = new Transaction($command);

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
        $command = new Transactional();
        $transaction = new Transaction($command);

        $this->assertEmpty($transaction->arguments(), 'When the proxied method return something other than the command the proxy should return the response.');
        $this->assertSame($transaction, $transaction->arguments(['foo' => 'bar']), 'When a proxied method is for a fluent method then the proxy should be returned instead.');
        $this->assertSame('bar', $transaction->argument('foo'), 'When the proxied method return something other than the command the proxy should return the response.');
        $this->assertTrue($transaction->test(), 'When the proxied method return something other than the command the proxy should return the response.');
    }
}
