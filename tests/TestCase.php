<?php

namespace ArtisanSdk\CQRS\Tests;

use ArtisanSdk\CQRS\Tests\Fakes\Database\Connection;
use ArtisanSdk\CQRS\Tests\Fakes\Dispatcher as Bus;
use ArtisanSdk\CQRS\Tests\Fakes\Events\Dispatcher as Events;
use Illuminate\Container\Container;
use Illuminate\Contracts\Bus\Dispatcher as BusInterface;
use Illuminate\Contracts\Container\Container as ContainerInterface;
use Illuminate\Contracts\Events\Dispatcher as EventsInterface;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Translation\FileLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\Factory;
use PHPUnit\Framework\TestCase as PHPUnit;
use DMS\PHPUnitExtensions\ArraySubset\ArraySubsetAsserts;

class TestCase extends PHPUnit
{
    use ArraySubsetAsserts;

    protected $app;

    /**
     * Setup tests.
     */
    public function setUp() : void
    {
        $this->createApplication();
    }

    /**
     * Create application dependencies required for testing.
     */
    public function createApplication()
    {
        $this->app = Container::getInstance();
        $this->app->singleton(ContainerInterface::class, function() {
            return $this->app;
        });

        // Bind the fakes into the container for the tests
        $this->app->singleton(BusInterface::class, Bus::class);
        $this->app->bind(ConnectionInterface::class, Connection::class);
        $this->app->bind(EventsInterface::class, Events::class);

        // Provide the validator with the translatior for error messages
        $this->app->singleton('files', function () {
            return new Filesystem();
        });
        $this->app->singleton('translation.loader', function ($app) {
            return new FileLoader($app['files'], realpath(__DIR__.'/../resources/lang'));
        });
        $this->app->singleton('translator', function ($app) {
            return new Translator($app['translation.loader'], 'en');
        });
        $this->app->singleton('validator', function ($app) {
            return new Factory($app['translator'], $app);
        });

        // Provide a cache config for the CacheManager
        $this->app->singleton('config', function () {
            return [
                'cache.default'      => 'array',
                'cache.stores.array' => [
                    'driver' => 'array',
                    'prefix' => 'test',
                ],
            ];
        });
    }
}
