<?php

namespace ArtisanSdk\CQRS\Tests\Unit\Queries;

use ArtisanSdk\Contract\Invokable;
use ArtisanSdk\Contract\Query as QueryInterface;
use ArtisanSdk\Contract\Runnable;
use ArtisanSdk\CQRS\Builder;
use ArtisanSdk\CQRS\Tests\Fakes\Queries\Base as Query;
use ArtisanSdk\CQRS\Tests\TestCase;

class QueryTest extends TestCase
{
    /**
     * Test that a query can be made.
     */
    public function testFactoryMake()
    {
        $query = Query::make();

        $this->assertInstanceOf(Builder::class, $query, 'When a query is made it should run through the dispatcher and return as a builder.');
        $this->assertInstanceOf(QueryInterface::class, $query->toBase(), 'A query must implement the '.QueryInterface::class.' interface.');
        $this->assertInstanceOf(Query::class, $query->toBase(), 'When a query is made it should return a factory instance of itself.');
        $this->assertEmpty($query->arguments(), 'When a query is made without arguments then the arguments should be an empty array.');

        $arguments = ['foo' => 'bar'];
        $query = Query::make($arguments);
        $this->assertSame($arguments, $query->arguments(), 'When a query is made with arguments then the arguments should be assigned to the returned query.');
    }

    /**
     * Test that a query can be invoked.
     */
    public function testIsInvokable()
    {
        $query = new Query();

        $this->assertInstanceOf(Invokable::class, $query, 'A query must implement the '.Invokable::class.' interface.');
        $this->assertInstanceOf(Runnable::class, $query, 'A query must implement the '.Runnable::class.' interface.');
        $this->assertEquals($query->run(), $query(), 'When a query is invoked it should run.');
    }
}
