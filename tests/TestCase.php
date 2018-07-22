<?php

namespace ArtisanSdk\CQRS\Tests;

use ArtisanSdk\CQRS\Tests\Fakes\Bus\Dispatcher as Bus;
use ArtisanSdk\CQRS\Tests\Fakes\Database\Connection;
use ArtisanSdk\CQRS\Tests\Fakes\Events\Dispatcher as Events;
use Illuminate\Container\Container;
use Illuminate\Contracts\Bus\Dispatcher as BusInterface;
use Illuminate\Contracts\Container\Container as ContainerInterface;
use Illuminate\Contracts\Events\Dispatcher as EventsInterface;
use Illuminate\Database\ConnectionInterface;
use PHPUnit\Framework\TestCase as PHPUnit;

class TestCase extends PHPUnit
{
    protected $app;

    /**
     * Setup tests.
     */
    public function setUp()
    {
        $this->createApplication();
    }

    /**
     * Create application dependencies required for testing.
     */
    public function createApplication()
    {
        $this->app = Container::getInstance();
        $this->app->singleton(ContainerInterface::class, $this->app);
        $this->app->singleton(BusInterface::class, Bus::class);
        $this->app->bind(ConnectionInterface::class, Connection::class);
        $this->app->bind(EventsInterface::class, Events::class);
    }
}
