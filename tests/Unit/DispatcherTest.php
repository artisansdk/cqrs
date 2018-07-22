<?php

namespace ArtisanSdk\CQRS\Tests\Unit;

use ArtisanSdk\Contract\Command as CommandInterface;
use ArtisanSdk\Contract\Query as QueryInterface;
use ArtisanSdk\Contract\Runnable as RunnableInterface;
use ArtisanSdk\CQRS\Builder;
use ArtisanSdk\CQRS\Commands\Evented;
use ArtisanSdk\CQRS\Commands\Transaction;
use ArtisanSdk\CQRS\Dispatcher;
use ArtisanSdk\CQRS\Events\Event;
use ArtisanSdk\CQRS\Tests\Fakes\Commands\Command;
use ArtisanSdk\CQRS\Tests\Fakes\Commands\Eventable as EventableCommand;
use ArtisanSdk\CQRS\Tests\Fakes\Commands\Foo;
use ArtisanSdk\CQRS\Tests\Fakes\Commands\Foo\Fizz;
use ArtisanSdk\CQRS\Tests\Fakes\Commands\Runnable;
use ArtisanSdk\CQRS\Tests\Fakes\Commands\Transactional;
use ArtisanSdk\CQRS\Tests\Fakes\Events\Baz;
use ArtisanSdk\CQRS\Tests\Fakes\Events\Fizzing;
use ArtisanSdk\CQRS\Tests\Fakes\Events\Foo\Bar;
use ArtisanSdk\CQRS\Tests\Fakes\Queries\Eventable as EventableQuery;
use ArtisanSdk\CQRS\Tests\Fakes\Queries\Query;
use ArtisanSdk\CQRS\Tests\TestCase;
use InvalidArgumentException;

class DispatcherTest extends TestCase
{
    /**
     * A dispatcher instance.
     *
     * @var \ArtisanSdk\CQRS\Dispatcher
     */
    protected $dispatcher;

    /**
     * Setup tests.
     */
    public function setUp()
    {
        parent::setUp();

        $this->dispatcher = Dispatcher::make();
    }

    /**
     * Test a static factory.
     */
    public function testMake()
    {
        $this->assertInstanceOf(Dispatcher::class, $this->dispatcher, 'The dispatcher\'s factory constructor make() method should return an instance of a dispatcher.');
    }

    /**
     * Test runnable can be dispatched.
     */
    public function testDispatchRunnable()
    {
        $runnable = $this->dispatcher->dispatch(new Runnable());

        $this->assertNotInstanceOf(Builder::class, $runnable, 'A runnable should be dispatched but not wrapped in a builder.');
        $this->assertInstanceOf(RunnableInterface::class, $runnable, 'The dispatched runnable should be an instance of a runnable.');

        $runnable = $this->dispatcher->dispatch(Runnable::class);

        $this->assertNotInstanceOf(Builder::class, $runnable, 'A runnable class name should be dispatched but not wrapped in a builder.');
        $this->assertInstanceOf(RunnableInterface::class, $runnable, 'The dispatched runnable should be an instance of a runnable.');
    }

    /**
     * Test commands can be dispatched.
     */
    public function testDispatchCommand()
    {
        $builder = $this->dispatcher->dispatch(new Command());

        $this->assertInstanceOf(Builder::class, $builder, 'A command should be dispatched and wrapped in a builder.');
        $this->assertInstanceOf(CommandInterface::class, $builder->toBase(), 'The dispatched command should be an instance of a command.');

        $builder = $this->dispatcher->dispatch(Command::class);

        $this->assertInstanceOf(Builder::class, $builder, 'A command class name should be dispatched and wrapped in a builder.');
        $this->assertInstanceOf(CommandInterface::class, $builder->toBase(), 'The dispatched command should be an instance of a command.');
    }

    /**
     * Test queries can be dispatched.
     */
    public function testDispatchQuery()
    {
        $builder = $this->dispatcher->dispatch(new Query());

        $this->assertInstanceOf(Builder::class, $builder, 'A query should be dispatched and wrapped in a builder.');
        $this->assertInstanceOf(QueryInterface::class, $builder->toBase(), 'The dispatched query should be an instance of a query.');

        $builder = $this->dispatcher->dispatch(Query::class);

        $this->assertInstanceOf(Builder::class, $builder, 'A query class name should be dispatched and wrapped in a builder.');
        $this->assertInstanceOf(QueryInterface::class, $builder->toBase(), 'The dispatched query should be an instance of a query.');
    }

    /**
     * Test invalid runnable throws an exception.
     */
    public function testInvalidRunnableFails()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('stdClass must be an instance of ArtisanSdk\Contract\Runnable.');

        $this->dispatcher->dispatch(new \stdClass());
    }

    /**
     * Test invalid command throws an exception.
     */
    public function testInvalidCommandFails()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('ArtisanSdk\CQRS\Tests\Fakes\Queries\Query must be an instance of ArtisanSdk\Contract\Command.');

        $this->dispatcher->command(new Query());
    }

    /**
     * Test invalid query throws an exception.
     */
    public function testInvalidQueryFails()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('ArtisanSdk\CQRS\Tests\Fakes\Commands\Command must be an instance of ArtisanSdk\Contract\Query.');

        $this->dispatcher->query(new Command());
    }

    /**
     * Test a command can be transactional.
     */
    public function testTransactionalCommand()
    {
        $builder = $this->dispatcher->dispatch(new Transactional());

        $this->assertInstanceOf(Builder::class, $builder, 'A transactional should be dispatched and wrapped in a builder.');
        $this->assertInstanceOf(Transaction::class, $builder->toBase(), 'The dispatched transactional should be a transaction.');

        $builder = $this->dispatcher->dispatch(Transactional::class);

        $this->assertInstanceOf(Builder::class, $builder, 'A transactional class name should be dispatched and wrapped in a builder.');
        $this->assertInstanceOf(Transaction::class, $builder->toBase(), 'The dispatched transactional should be a transaction.');
    }

    /**
     * Test a command can be evented.
     */
    public function testEventableCommand()
    {
        $builder = $this->dispatcher->dispatch(new EventableCommand());

        $this->assertInstanceOf(Builder::class, $builder, 'An eventable should be dispatched and wrapped in a builder.');
        $this->assertInstanceOf(Evented::class, $builder->toBase(), 'The dispatched eventable should be evented.');

        $builder = $this->dispatcher->dispatch(EventableCommand::class);

        $this->assertInstanceOf(Builder::class, $builder, 'An eventable class name should be dispatched and wrapped in a builder.');
        $this->assertInstanceOf(Evented::class, $builder->toBase(), 'The dispatched eventable should be evented.');
    }

    /**
     * Test a query can be evented.
     */
    public function testEventableQuery()
    {
        $builder = $this->dispatcher->dispatch(new EventableQuery());

        $this->assertInstanceOf(Builder::class, $builder, 'An eventable should be queried and wrapped in a builder.');
        $this->assertInstanceOf(Evented::class, $builder->toBase(), 'The queried eventable should be evented.');

        $builder = $this->dispatcher->dispatch(EventableQuery::class);

        $this->assertInstanceOf(Builder::class, $builder, 'An eventable class name should be queried and wrapped in a builder.');
        $this->assertInstanceOf(Evented::class, $builder->toBase(), 'The queried eventable should be evented.');
    }

    /**
     * Test an event can be fired.
     */
    public function testEvent()
    {
        $event = new Event();
        $events = $this->dispatcher->event($event, ['foo' => 'bar']);

        $this->assertSame($event, $events[0]['class'], 'The event should have been dispatched.');
        $this->assertSame(['foo' => 'bar'], $events[0]['payload'], 'The event payload should have been received by the dispatcher.');
        $this->assertFalse($events[0]['halt'], 'The event should not halt if fired.');

        $events = $this->dispatcher->event('foo.bar', ['foo' => 'bar']);

        $this->assertSame('foo.bar', $events[0]['name'], 'The event name should have been dispatched.');
        $this->assertSame(['foo' => 'bar'], $events[0]['payload'], 'The event payload should have been received by the dispatcher.');
        $this->assertFalse($events[0]['halt'], 'The event should not halt if fired.');
    }

    /**
     * Test an event can be fired until halted..
     */
    public function testEventUntil()
    {
        $event = new Event();
        $events = $this->dispatcher->until($event, ['foo' => 'bar']);

        $this->assertSame($event, $events[0]['class'], 'The event should have been dispatched.');
        $this->assertSame(['foo' => 'bar'], $events[0]['payload'], 'The event payload should have been received by the dispatcher.');
        $this->assertTrue($events[0]['halt'], 'The event should halt if fired.');

        $events = $this->dispatcher->until('foo.bar', ['foo' => 'bar']);

        $this->assertSame('foo.bar', $events[0]['name'], 'The event name should have been dispatched.');
        $this->assertSame(['foo' => 'bar'], $events[0]['payload'], 'The event payload should have been received by the dispatcher.');
        $this->assertTrue($events[0]['halt'], 'The event should halt if fired.');
    }

    /**
     * Test an event can be fired dynamically as a progressive tense (until) event.
     */
    public function testProgressiveTenseEvent()
    {
        $class = new Foo();
        $events = $this->dispatcher->creating($class);

        $this->assertSame(Event::class, $events[0]['name'], 'The default event should have fired.');
        $this->assertInstanceOf(Event::class, $events[0]['class'], 'The event fired should be an instance of '.Event::class.'.');
        $this->assertSame('ArtisanSdk\CQRS\Tests\Fakes\Events\Foo\Creating', $events[0]['class']->event(), 'The event should be named based on the method called.');
        $this->assertSame($class, $events[0]['class']->properties()['payload'], 'The event should be passed the class as the payload.');
        $this->assertTrue($events[0]['halt'], 'The event should halt if event name is progressive tense.');
    }

    /**
     * Test an event can be fired dynamically as a past (fire) event.
     */
    public function testPastTenseEvent()
    {
        $class = new Foo();
        $events = $this->dispatcher->created($class);

        $this->assertSame(Event::class, $events[0]['name'], 'The default event should have fired.');
        $this->assertInstanceOf(Event::class, $events[0]['class'], 'The event fired should be an instance of '.Event::class.'.');
        $this->assertSame('ArtisanSdk\CQRS\Tests\Fakes\Events\Foo\Created', $events[0]['class']->event(), 'The event should be named based on the method called.');
        $this->assertSame($class, $events[0]['class']->properties()['payload'], 'The event should be passed the class as the payload.');
        $this->assertFalse($events[0]['halt'], 'The event should not halt if event name is past tense.');
    }

    /**
     * Test an event can be fired dynamically and resolved to an existing one based on convention.
     */
    public function testExistingEvent()
    {
        $class = new Foo();
        $events = $this->dispatcher->bar($class);

        $this->assertSame(Bar::class, $events[0]['name'], 'The bar event should have fired.');
        $this->assertInstanceOf(Bar::class, $events[0]['class'], 'The event fired should be an instance of '.Bar::class.'.');
        $this->assertSame(Bar::class, $events[0]['class']->event(), 'The event should be named based on the method called.');
        $this->assertSame($class, $events[0]['class']->properties()['payload'], 'The event should be passed the class as the payload.');
        $this->assertFalse($events[0]['halt'], 'The event should not halt if event name is past tense.');
    }

    /**
     * Test an event can be fired dynamically and resolved to a fallback one based on convention.
     */
    public function testFallbackEvent()
    {
        $class = new Foo();
        $events = $this->dispatcher->baz($class);

        $this->assertSame(Baz::class, $events[0]['name'], 'The baz event should have fired.');
        $this->assertInstanceOf(Baz::class, $events[0]['class'], 'The event fired should be an instance of '.Baz::class.'.');
        $this->assertSame('ArtisanSdk\CQRS\Tests\Fakes\Events\Foo\Baz', $events[0]['class']->event(), 'The event should be named based on the method called.');
        $this->assertSame($class, $events[0]['class']->properties()['payload'], 'The event should be passed the class as the payload.');
        $this->assertFalse($events[0]['halt'], 'The event should not halt if event name is past tense.');
    }

    /**
     * Test an event can be fired dynamically and resolved to a default one based on convention.
     */
    public function testDefaultEvent()
    {
        $class = new Fizz();
        $events = $this->dispatcher->fizzing($class);

        $this->assertSame(Fizzing::class, $events[0]['name'], 'The fizzing event should have fired.');
        $this->assertInstanceOf(Fizzing::class, $events[0]['class'], 'The event fired should be an instance of '.Fizzing::class.'.');
        $this->assertSame('ArtisanSdk\CQRS\Tests\Fakes\Events\Foo\Fizz\Fizzing', $events[0]['class']->event(), 'The event should be named based on the method called.');
        $this->assertSame($class, $events[0]['class']->properties()['payload'], 'The event should be passed the class as the payload.');
        $this->assertTrue($events[0]['halt'], 'The event should halt if event name is progressive tense.');
    }
}
