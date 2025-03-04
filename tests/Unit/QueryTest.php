<?php

declare(strict_types=1);

namespace ArtisanSdk\CQRS\Tests\Unit;

use ArtisanSdk\CQRS\Builder;
use ArtisanSdk\CQRS\Tests\TestCase;
use ArtisanSdk\CQRS\Tests\Fakes\Queries\Builderless as BuilderlessQuery;
use ArtisanSdk\CQRS\Tests\Fakes\Queries\Base as Query;
use ArtisanSdk\Contract\{Invokable, Query as QueryInterface, Runnable};
use BadMethodCallException;
use Illuminate\Pagination\LengthAwarePaginator;

class QueryTest extends TestCase
{
    /**
     * Test that a query can be made.
     */
    public function test_factory_make()
    {
        $query = Query::make();

        $this->assertInstanceOf(Builder::class, $query, 'When a query is made it should run through the dispatcher and return as a builder.');
        $this->assertInstanceOf(QueryInterface::class, $query->toBase(), 'A query must implement the ' . QueryInterface::class . ' interface.');
        $this->assertInstanceOf(Query::class, $query->toBase(), 'When a query is made it should return a factory instance of itself.');
        $this->assertEmpty($query->arguments(), 'When a query is made without arguments then the arguments should be an empty array.');

        $arguments = ['foo' => 'bar'];
        $query = Query::make($arguments);
        $this->assertSame($arguments, $query->arguments(), 'When a query is made with arguments then the arguments should be assigned to the returned query.');
    }

    /**
     * Test that a query can be invoked.
     */
    public function test_is_invokable()
    {
        $query = new Query;

        $this->assertInstanceOf(Invokable::class, $query, 'A query must implement the ' . Invokable::class . ' interface.');
        $this->assertInstanceOf(Runnable::class, $query, 'A query must implement the ' . Runnable::class . ' interface.');
        $this->assertEquals($query->run(), $query(), 'When a query is invoked it should run.');
    }


    public function test_can_paginate_builderless_query()
    {
        $query = new BuilderlessQuery;

        $pagination = $query->paginate();

        $this->assertInstanceOf(LengthAwarePaginator::class, $pagination);

        $this->expectException(BadMethodCallException::class);
        $query->toSql();
    }
}
