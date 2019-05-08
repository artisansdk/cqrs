<?php

namespace ArtisanSdk\CQRS\Tests\Unit\Buses;

use ArtisanSdk\Contract\Eventable as Contract;
use ArtisanSdk\Contract\Invokable;
use ArtisanSdk\Contract\Runnable;
use ArtisanSdk\CQRS\Buses\Evented;
use ArtisanSdk\CQRS\Dispatcher;
use ArtisanSdk\CQRS\Tests\Fakes\Commands\Custom;
use ArtisanSdk\CQRS\Tests\Fakes\Commands\Eventable;
use ArtisanSdk\CQRS\Tests\Fakes\Database\Connection;
use ArtisanSdk\CQRS\Tests\Fakes\Events\Dispatcher as Events;
use ArtisanSdk\CQRS\Tests\Fakes\Events\Fizzed;
use ArtisanSdk\CQRS\Tests\Fakes\Events\Fizzing;
use ArtisanSdk\CQRS\Tests\TestCase;
use ArtisanSdk\Event\Event;
use Illuminate\Contracts\Events\Dispatcher as EventsInterface;

class EventedTest extends TestCase
{
    /**
     * Test that an evented command can be invoked.
     */
    public function testIsInvokable()
    {
        $command = new Eventable();
        $evented = new Evented($command, new Dispatcher($this->app), new Connection());

        $this->assertInstanceOf(Contract::class, $command, 'A command that should be fire events must implement the '.Contract::class.' interface.');
        $this->assertInstanceOf(Invokable::class, $evented, 'An evented command must implement the '.Invokable::class.' interface.');
        $this->assertInstanceOf(Runnable::class, $evented, 'An evented command must implement the '.Runnable::class.' interface.');
        $this->assertSame($evented->run(), $evented(), 'When an evented command is invoked it should run the command.');
        $this->assertSame($evented->run(), $command(), 'When an evented command is invoked it should run the command.');
    }

    /**
     * Test that an evented command proxies responses.
     */
    public function testResponseIsProxied()
    {
        $evented = new Evented(new Eventable(), new Dispatcher($this->app), new Connection());

        $this->assertEmpty($evented->arguments(), 'When the proxied method return something other than the command the proxy should return the response.');
        $this->assertSame($evented, $evented->arguments(['foo' => 'bar']), 'When a proxied method is for a fluent method then the proxy should be returned instead.');
        $this->assertSame('bar', $evented->argument('foo'), 'When the proxied method return something other than the command the proxy should return the response.');
        $this->assertTrue($evented->test(), 'When the proxied method return something other than the command the proxy should return the response.');
    }

    /**
     * Test that an evented command fires the after event when the command is not aborted.
     */
    public function testAfterEventWhenNotAborted()
    {
        $dispatcher = new Events();
        $this->app->singleton(EventsInterface::class, function () use ($dispatcher) {
            return $dispatcher;
        });
        $evented = new Evented(new Eventable(), new Dispatcher($this->app), new Connection());
        $response = $evented->run();
        $events = $dispatcher->events;

        $this->assertCount(2, $events[Event::class], 'The evented command should have fired a before and after event.');
        $this->assertTrue($response, 'The command response should have been returned.');
    }

    /**
     * Test that an evented command is silent when the command is aborted.
     */
    public function testSilentWhenAborted()
    {
        $dispatcher = new Events();
        $this->app->singleton(EventsInterface::class, function () use ($dispatcher) {
            return $dispatcher;
        });
        $evented = new Evented(new Eventable(), new Dispatcher($this->app), new Connection());
        $response = $evented->abort()->run();
        $events = $dispatcher->events;

        $this->assertCount(1, $events[Event::class], 'The aborted evented command should have fired only the before event.');
        $this->assertTrue($response, 'The aborted command response should have been returned.');
    }

    /**
     * Test that an evented command is silent when the command is silenced.
     */
    public function testSilentWhenSilenced()
    {
        $dispatcher = new Events();
        $this->app->singleton(EventsInterface::class, function () use ($dispatcher) {
            return $dispatcher;
        });
        $evented = new Evented(new Eventable(), new Dispatcher($this->app), new Connection());
        $response = $evented->silently();
        $events = $dispatcher->events;

        $this->assertCount(0, $events, 'The silenced evented command should fire no events.');
        $this->assertTrue($response, 'The silenced command response should have been returned.');
    }

    /**
     * Test that the default "executed" event name is resolved for past tense events.
     */
    public function testDefaultEventName()
    {
        $evented = new Evented(new Eventable(), new Dispatcher($this->app), new Connection());

        $this->assertSame('executed', $evented->resolvePastTense('foo'), 'The default "executed" event should be resolved for past tense names.');
    }

    /**
     * Test that custom events can be fired.
     */
    public function testCustomEvents()
    {
        $dispatcher = new Events();
        $this->app->singleton(EventsInterface::class, function () use ($dispatcher) {
            return $dispatcher;
        });
        $evented = new Evented(new Custom(), new Dispatcher($this->app), new Connection());
        $response = $evented->run();
        $events = $dispatcher->events;

        $this->assertCount(2, $events, 'The evented command should have fired the custom before and after events.');
        $this->assertCount(1, $events[Fizzing::class], 'The evented command should have fired the custom fizzing before event.');
        $this->assertCount(1, $events[Fizzed::class], 'The evented command should have fired the custom fizzed after event.');
        $this->assertTrue($response, 'The command response should have been returned.');
    }
}
