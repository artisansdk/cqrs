<?php

namespace ArtisanSdk\CQRS\Tests\Unit\Traits;

use ArtisanSdk\CQRS\Builder;
use ArtisanSdk\CQRS\Commands\Transaction;
use ArtisanSdk\CQRS\Dispatcher;
use ArtisanSdk\CQRS\Events\Event;
use ArtisanSdk\CQRS\Tests\Fakes\Commands\Command;
use ArtisanSdk\CQRS\Tests\Fakes\Commands\Transactional;
use ArtisanSdk\CQRS\Tests\Fakes\Events\Dispatcher as Events;
use ArtisanSdk\CQRS\Tests\Fakes\Queries\Query;
use ArtisanSdk\CQRS\Tests\TestCase;

class CQRSTest extends TestCase
{
    /**
     * Test that a dispatcher can be made.
     */
    public function testDispatcher()
    {
        $this->assertInstanceOf(Dispatcher::class, (new Command())->testDispatcher(), 'A call to dispatcher() should return an instance of a dispatcher.');
    }

    /**
     * Test that a call to dispatch dispatches the class.
     */
    public function testCall()
    {
        $response = (new Command())->testCall(Transactional::class);

        $this->assertInstanceOf(Builder::class, $response, 'A call to call() should dispatch an instance of the command and return a builder.');
        $this->assertInstanceOf(Transaction::class, $response->toBase(), 'The builder should wrap the command passed to call().');
    }

    /**
     * Test that a call to command dispatches the command.
     */
    public function testCommand()
    {
        $response = (new Command())->testCommand(Command::class);

        $this->assertInstanceOf(Builder::class, $response, 'A call to command() should dispatch an instance of the command and return a builder.');
        $this->assertInstanceOf(Command::class, $response->toBase(), 'The builder should wrap the command passed to command().');
    }

    /**
     * Test that a call to query dispatches the query.
     */
    public function testQuery()
    {
        $response = (new Query())->testQuery(Query::class);

        $this->assertInstanceOf(Builder::class, $response, 'A call to query() should dispatch an instance of the query and return a builder.');
        $this->assertInstanceOf(Query::class, $response->toBase(), 'The builder should wrap the query passed to query().');
    }

    /**
     * Test that a call to event dispatches the event.
     */
    public function testEvent()
    {
        $dispatcher = new Events();
        $this->app->bind(DispatcherInterface::class, $dispatcher);
        $response = (new Command())->testEvent(new Event());
        $event = array_shift($response);

        $this->assertSame(Event::class, $event['name'], 'The event that should have been dispatched should match the event passed to event().');
        $this->assertFalse($event['halt'], 'The event should not halt when dispatched from event().');
    }

    /**
     * Test that a call to until dispatches the event until an event halts.
     */
    public function testUntil()
    {
        $dispatcher = new Events();
        $this->app->bind(DispatcherInterface::class, $dispatcher);
        $response = (new Command())->testUntil(new Event());
        $event = array_shift($response);

        $this->assertSame(Event::class, $event['name'], 'The event that should have been dispatched should match the event passed to event().');
        $this->assertTrue($event['halt'], 'The event should halt when dispatched from until().');
    }
}
