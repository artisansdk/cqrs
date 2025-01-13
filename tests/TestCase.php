<?php

declare(strict_types=1);

namespace ArtisanSdk\CQRS\Tests;

use ArtisanSdk\CQRS\Tests\Fakes\Database\Connection;
use ArtisanSdk\CQRS\Tests\Fakes\Dispatcher as Bus;
use ArtisanSdk\CQRS\Tests\Fakes\Events\Dispatcher as Events;
use DMS\PHPUnitExtensions\ArraySubset\ArraySubsetAsserts;
use Illuminate\Container\Container;
use Illuminate\Contracts\Bus\Dispatcher as BusInterface;
use Illuminate\Contracts\Container\Container as ContainerInterface;
use Illuminate\Contracts\Events\Dispatcher as EventsInterface;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Translation\{FileLoader, Translator};
use Illuminate\Validation\Factory;
use PHPUnit\Framework\TestCase as PHPUnit;

class TestCase extends PHPUnit
{
    use ArraySubsetAsserts;

    protected $app;

    /**
     * Setup tests.
     */
    protected function setUp(): void
    {
        $this->createApplication();
    }

    /**
     * Create application dependencies required for testing.
     */
    public function createApplication()
    {
        $this->app = Container::getInstance();
        $this->app->singleton(ContainerInterface::class, fn () => $this->app);

        // Bind the fakes into the container for the tests
        $this->app->singleton(BusInterface::class, Bus::class);
        $this->app->bind(ConnectionInterface::class, Connection::class);
        $this->app->bind(EventsInterface::class, Events::class);

        // Provide the validator with the translator for error messages
        $this->app->singleton('files', fn () => new Filesystem);
        $this->app->singleton('translation.loader', fn ($app) => new FileLoader($app['files'], realpath(__DIR__.'/../resources/lang')));
        $this->app->singleton('translator', fn ($app) => new Translator($app['translation.loader'], 'en'));
        $this->app->singleton('validator', fn ($app) => new Factory($app['translator'], $app));

        // Provide a cache config for the CacheManager
        $this->app->singleton('config', fn () => [
            'cache.default' => 'array',
            'cache.stores.array' => [
                'driver' => 'array',
                'prefix' => 'test',
            ],
        ]);
    }
}
