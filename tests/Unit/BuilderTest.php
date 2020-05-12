<?php

namespace ArtisanSdk\CQRS\Tests\Unit;

use ArtisanSdk\Contract\Event;
use ArtisanSdk\Contract\Invokable;
use ArtisanSdk\Contract\Runnable;
use ArtisanSdk\CQRS\Builder;
use ArtisanSdk\CQRS\Concerns\Arguments;
use ArtisanSdk\CQRS\Concerns\Silencer;
use ArtisanSdk\CQRS\Jobs\Pending;
use ArtisanSdk\CQRS\Tests\Fakes\Commands\Command;
use ArtisanSdk\CQRS\Tests\Fakes\Commands\Queueable as QueueableFake;
use ArtisanSdk\CQRS\Tests\Fakes\Commands\Runnable as RunnableFake;
use ArtisanSdk\CQRS\Tests\Fakes\Queries\Query;
use ArtisanSdk\CQRS\Tests\TestCase;
use BadMethodCallException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Arr;
use InvalidArgumentException;

class BuilderTest extends TestCase
{
    /**
     * Test that the builder implements the required behaviors.
     */
    public function testImplementsBehavior()
    {
        $runnable = new RunnableFake();
        $builder = new Builder($runnable);

        $this->assertInstanceOf(Invokable::class, $builder, 'The builder must implement the '.Invokable::class.' interface.');
        $this->assertInstanceOf(Runnable::class, $builder, 'The builder must implement the '.Runnable::class.' interface.');
        $this->assertSame([Arguments::class, Silencer::class], array_values(class_uses($builder)), 'The builder should support arguments and being silenced.');
    }

    /**
     * Test that a command can be built with the builder.
     */
    public function testCommandCanBeBuilt()
    {
        $command = new Command();

        $this->assertInstanceOf(Runnable::class, $command, 'A command must implement the '.Runnable::class.' interface to be passed to the builder.');

        $builder = new Builder($command);

        $this->assertSame($command(), $builder(), 'When a command builder is invoked it should invoke the command.');
        $this->assertSame($command->run(), $builder->run(), 'When a command builder is ran it should run the command.');
    }

    /**
     * Test that a query can be built with the builder.
     */
    public function testQueryCanBeBuilt()
    {
        $query = new Query();

        $this->assertInstanceOf(Runnable::class, $query, 'A query must implement the '.Runnable::class.' interface to be passed to the builder.');

        $builder = new Builder($query);

        $this->assertSame($query(), $builder(), 'When a query builder is invoked it should invoke the query.');
        $this->assertSame($query->run(), $builder->run(), 'When a query builder is ran it should run the query.');
        $this->assertSame($query->get(), $builder->get(), 'When a query builder is gotten it should get the query.');
        $this->assertSame($query->toSql(), $builder->toSql(), 'When a query builder is converted to SQL, it should return the query\'s SQL.');
        $this->assertInstanceOf(LengthAwarePaginator::class, $builder->paginate(), 'When a query builder is paginated it should paginate the query.');
        $this->assertInstanceOf(QueryBuilder::class, $builder->builder(), 'When a query builder receives a call for a builder, it should forward to getting the query\'s builder.');
    }

    /**
     * Test that a command receives arguments.
     */
    public function testCommandReceivesArguments()
    {
        $original = new Command();
        $builder = new Builder($original);
        $command = $builder->foo('bar')->toBase();

        $this->assertSame($original, $command);
        $this->assertSame(['foo' => 'bar'], $builder->arguments(), 'The command builder should have a "foo" argument with a value of "bar".');
        $this->assertSame(['foo' => 'bar'], $command->arguments(), 'The command should have received the builder\'s arguments.');
    }

    /**
     * Test that a query receives arguments.
     */
    public function testQueryReceivesArguments()
    {
        $original = new Query();
        $builder = new Builder($original);
        $query = $builder->foo('bar')->toBase();

        $this->assertSame($original, $query);
        $this->assertSame(['foo' => 'bar'], $builder->arguments(), 'The query builder should have a "foo" argument with a value of "bar".');
        $this->assertSame(['foo' => 'bar'], $query->arguments(), 'The query should have received the builder\'s arguments.');
    }

    /**
     * Test that a builder gets first argument or null.
     */
    public function testFirstArgumentOrNull()
    {
        $original = new Command();
        $builder = new Builder($original);
        $command = $builder->foo()->bar('first', 'second')->toBase();

        $this->assertSame($original, $command);
        $this->assertSame(['foo' => null, 'bar' => 'first'], $builder->arguments(), 'The query builder should have a "foo" argument with a value of "null" and a "bar" argument with just the "first" value.');
        $this->assertSame('first', $command->argument('bar'), 'The command should for the "bar" argument the "first" value.');

        $this->expectException(InvalidArgumentException::class, 'The command should have thrown an exception because "foo" argument was null and is a required argument.');
        $command->argument('foo');
    }

    /**
     * Test that a runnable is silenced when the builder is silenced.
     */
    public function testRunnableIsSilenced()
    {
        $runnable = new RunnableFake();
        $builder = new Builder($runnable);
        $runnable = $builder->silence()->toBase();

        $this->assertTrue($builder->silenced(), 'The builder should be silenced.');
        $this->assertTrue($runnable->silenced(), 'The runnable should be silenced when the builder is silenced.');
    }

    /**
     * Test that a method call fails to be forwarded to a runnable that is not an instance of the class.
     */
    public function testBadMethodCallFailsToForward()
    {
        $runnable = new RunnableFake();
        $builder = new Builder($runnable);

        $this->expectException(BadMethodCallException::class);
        $this->expectExceptionMessage('Only call toSql() on ArtisanSdk\Contract\Query instances.');

        $builder->toSql();
    }

    /**
     * Test that queue method call forwards to the queueable with a generic event containing all the arguments as properties.
     */
    public function testQueueForwardsToQueueable()
    {
        $queueable = new QueueableFake();
        $builder = new Builder($queueable);
        $job = $builder->foo('bar')->queue();

        $this->assertInstanceOf(Event::class, $queueable->event, 'The queuable command should receive an event.');
        $this->assertArraySubset($builder->arguments(), Arr::except($queueable->event->properties(), 'event'), 'The event should contain the builder arguments as properties.');
        $this->assertInstanceOf(Pending::class, $job, 'The queue method should return a pending job.');
    }

    /**
     * Test that a macro call can be registered and forwarded to the base runnable.
     */
    public function testMacroCanBeForwarded()
    {
        Builder::macro('test', function (...$arguments) {
            return $this->forwardToBase('test', Runnable::class, ...$arguments);
        });

        $runnable = new RunnableFake();
        $builder = new Builder($runnable);
        $builder->foo('bar');

        $this->assertSame(['foo' => 'bar'], $builder->arguments(), 'The builder should receive arguments.');
        $this->assertSame(['foo' => 'bar'], $builder->test(), 'The builder should forward builder arguments to base runnable.');
        $this->assertSame(['foo', 'bar'], $builder->test('foo', 'bar'), 'The builder should forward macro arguments to base runnable method.');
        $this->assertTrue($builder->run(), 'The builder should still be able to call the run method.');
    }
}
