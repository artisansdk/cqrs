<?php

declare(strict_types=1);

namespace ArtisanSdk\CQRS\Tests\Unit\Buses;

use ArtisanSdk\Contract\{Cacheable as Contract, Invokable, Runnable};
use ArtisanSdk\CQRS\Buses\Cached;
use ArtisanSdk\CQRS\Tests\Fakes\Queries\{Cacheable, Query as BaseQuery};
use ArtisanSdk\CQRS\Tests\TestCase;
use ArtisanSdk\CQRS\{Builder, Dispatcher};
use BadMethodCallException;
use Illuminate\Cache\Repository;
use Mockery;
use Mockery\MockInterface;
use RuntimeException;

class CachedTest extends TestCase
{
    public function test_is_invokable()
    {
        $query = new Cacheable;
        $cached = new Cached($query, new Dispatcher($this->app));

        $this->assertInstanceOf(Contract::class, $query, 'A cacheable query must implement the '.Contract::class.' interface.');
        $this->assertInstanceOf(Invokable::class, $cached, 'An evented query must implement the '.Invokable::class.' interface.');
        $this->assertInstanceOf(Runnable::class, $cached, 'An evented query must implement the '.Runnable::class.' interface.');
        $this->assertSame($cached->run(), $cached(), 'When an evented query is invoked it should run the query.');
        $this->assertSame($cached->run(), $query(), 'When an evented query is invoked it should run the query.');
    }

    public function test_cache_has_ttl()
    {
        $driver = $this->app->make(Repository::class);

        $query = new Cacheable;

        $query->ttl = 30;

        $query->tags = 'foo';

        $cached = new Builder(new Cached($query, new Dispatcher($this->app), $driver));

        $this->assertEquals($query->ttl, 30);

        $cached->ttl(60);

        $this->assertEquals(60, $query->ttl);

        // The following are not needed for this test
        $cached->cached();
        $cached->cache();
        $cached->nocache();
        $cached->fresh();
        $cached->refresh();
        $cached->invalidate();
        $cached->bust();
    }

    public function test_invalidate_throws_exception_when_no_tags_are_present()
    {
        $driver = $this->app->make(Repository::class);

        $query = new Cacheable;

        $this->expectException(RuntimeException::class);

        $cached = new Cached($query, new Dispatcher($this->app), $driver);

        $cached->invalidate();
    }

    public function test_cache_calls_put_when_misses()
    {
        $driver = Mockery::mock($this->app->make(Repository::class), function (MockInterface $mock) {

            $mock->makePartial()
                ->shouldReceive('has')
                ->once()
                ->andReturn(false);

            $mock->shouldReceive('put');
        });

        $query = new Cacheable;
        $cached = new Cached($query, new Dispatcher($this->app), $driver);

        $this->assertEquals('foo', $cached->get());
    }

    public function test_cache_gets_value_when_cache_has_value()
    {
        $driver = Mockery::mock($this->app->make(Repository::class), function (MockInterface $mock) {

            $mock->makePartial()
                ->shouldReceive('has')
                ->twice()
                ->andReturn(true);

            $mock->shouldReceive('get')
                ->twice()
                ->andReturn('foo');

            $mock->shouldNotReceive('put');
        });

        $query = new Cacheable;
        $cached = new Cached($query, new Dispatcher($this->app), $driver);

        $this->assertEquals('foo', $cached->get());

        $cached->key('foo');
        $cached->subKey('bar');
        $cached->paginate();
    }

    public function test_cache_calls_forever_when_query_is_cached_forever()
    {
        $driver = Mockery::mock($this->app->make(Repository::class), function (MockInterface $mock) {

            $mock->makePartial()
                ->shouldReceive('has')
                ->once()
                ->andReturn(false);

            $mock->shouldReceive('forever')
                ->once()
                ->andReturn(true);
        });

        $query = new Cacheable;

        $query->forever = true;

        $cached = new Cached($query, new Dispatcher($this->app), $driver);

        $this->assertEquals(true, $cached->forever());

        $cached->forever(true);

        $this->assertEquals('foo', $cached->get());
    }

    public function test_attempt_to_call_bust_throws_exception_when_not_instance_of_cachable()
    {
        $cached = (new Dispatcher($this->app))->query(new BaseQuery);

        $this->expectException(BadMethodCallException::class);

        $cached->bust();
    }

    public function test_dispatcher_makes_cachable_query_when_base_implements_cacheable()
    {
        $query = new Cacheable;
        $cached = (new Dispatcher($this->app))->query($query);

        $this->assertSame($cached->run(), $query->get());
    }
}
