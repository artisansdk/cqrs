<?php

declare(strict_types=1);

namespace ArtisanSdk\CQRS;

use ArtisanSdk\Contract\{Cacheable, Command, Eventable, Query, Runnable, Taggable, Transactional};
use ArtisanSdk\CQRS\Buses\{Cached, Evented, Transaction};
use ArtisanSdk\CQRS\Events\Event;
use Illuminate\Container\Container as App;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Events\Dispatcher as Events;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Str;
use InvalidArgumentException;
use InvalidArugmentException;

/**
 * Runnable Class Dispatcher (aka Command Bus).
 *
 * @example  $builder = Dispatcher::make()->command(Command::class)
 *           $builder = Dispatcher::make()->query(Command::class)
 *          $runnable = Dispatcher::make()->dispatch(Runnable::class)
 *                      Dispatcher::make()->creating(new User())
 */
class Dispatcher
{
    /**
     * Inject the application container into the dispatcher to resolve global services.
     *
     * @param  Container  $container
     */
    public function __construct(public Container $container)
    {
        $this->container = $container;
    }

    /**
     * Make an instance of the dispatcher.
     *
     * @return Dispatcher
     */
    public static function make()
    {
        return new static(App::getInstance());
    }

    /**
     * Dynamically forward methods as events.
     *
     * @example creating(new \ArtisanSdk\Model\Bar) => \ArtisanSdk\CQR\Events\Bar\Creating
     *          created(new \ArtisanSdk\Model\Bar) => \ArtisanSdk\CQR\Events\Bar\Created
     *          updating(new \ArtisanSdk\Model\Bar) => \ArtisanSdk\CQR\Events\Bar\Updating
     *          updated(new \ArtisanSdk\Model\Bar) => \ArtisanSdk\CQR\Events\Bar\Updated
     *          deleting(new \ArtisanSdk\Model\Bar) => \ArtisanSdk\CQR\Events\Bar\Deleting
     *          deleted(new \ArtisanSdk\Model\Bar) => \ArtisanSdk\CQR\Events\Bar\Deleted
     *          finding(new \ArtisanSdk\Model\Bar) => \ArtisanSdk\CQR\Events\Bar\Finding
     *          found(new \ArtisanSdk\Model\Bar) => \ArtisanSdk\CQR\Events\Bar\Found
     *          running(new \ArtisanSdk\Command\Bar) => \ArtisanSdk\CQR\Events\Bar\Running
     *          ran(new \ArtisanSdk\Command\Bar) => \ArtisanSdk\CQR\Events\Bar\Ran
     *          querying(new \ArtisanSdk\Query\Bar) => \ArtisanSdk\CQR\Events\Bar\Querying
     *          queried(new \ArtisanSdk\Query\Bar) => \ArtisanSdk\CQR\Events\Bar\Queried
     *
     * @param  string  $method
     * @param  array  $attributes
     * @return array|null
     */
    public function __call($method, $attributes = [])
    {
        $class = head($attributes);
        $classname = (string) (is_object($class) ? get_class($class) : $class);
        $position = $this->findOccurence($classname, ['Commands\\', 'Queries\\', 'Models\\']);
        $default = substr_replace($classname, 'Events\\'.Str::studly($method), $position);
        $name = $this->resolveEventClass($classname, $default);
        $fire = Str::endsWith($method, 'ing') ? 'until' : 'event';

        $event = (new $name(...$attributes))->event($this->normalizeEventClass($classname, $default));

        return $this->{$fire}($event);
    }

    /**
     * Dispatch a runnable command or query.
     *
     * @param  string|Runnable  $class
     * @return Runnable
     *
     * @throws InvalidArugmentException if class argument is not an instance of \ArtisanSdk\Contract\Runnable
     */
    public function dispatch($class)
    {
        $runnable = $this->resolveClass($class);

        if (! $runnable instanceof Runnable) {
            throw new InvalidArgumentException(get_class($class).' must be an instance of '.Runnable::class.'.');
        }

        if ($runnable instanceof Command) {
            return $this->command($runnable);
        }

        if ($runnable instanceof Query) {
            return $this->query($runnable);
        }

        return $runnable;
    }

    /**
     * Instantiate a command so it can be ran.
     *
     * @param  string|Runnable  $class
     * @return Builder<TCommand>
     *
     * @throws InvalidArgumentException if class argument is not an instance of \ArtisanSdk\Contract\Command
     */
    public function command($class)
    {
        $runnable = $this->resolveClass($class);

        if (! $runnable instanceof Command) {
            throw new InvalidArgumentException(get_class($runnable).' must be an instance of '.Command::class.'.');
        }

        $command = $runnable;

        if ($runnable instanceof Taggable) {
            $command = new Cached($command, $this);
        }

        if ($runnable instanceof Transactional) {
            $command = new Transaction($command, $this, $this->makeFromContainer(ConnectionInterface::class));
        }

        if ($runnable instanceof Eventable) {
            $command = new Evented($command, $this);
        }

        return $this->newBuilder($command);
    }

    /**
     * Instantiate a query so it can be ran.
     *
     * @param  string|Query  $class
     * @return Builder<TQuery>
     *
     * @throws InvalidArgumentException if class argument is not an instance of \ArtisanSdk\Contract\Query
     */
    public function query($class)
    {
        $runnable = $this->resolveClass($class);

        if (! $runnable instanceof Query) {
            throw new InvalidArgumentException(get_class($runnable).' must be an instance of '.Query::class.'.');
        }

        $query = $runnable;

        if ($runnable instanceof Cacheable) {
            $query = new Cached($query, $this);
        }

        if ($runnable instanceof Eventable) {
            $query = new Evented($query, $this);
        }

        return $this->newBuilder($query);
    }

    /**
     * Fire an event.
     *
     * @param  string|\ArtisanSdk\Contract\Event  $event
     * @param  array  $payload
     * @return array|null
     */
    public function event($event, $payload = [])
    {
        $events = $this->makeEvents();

        return method_exists($events, 'dispatch')
            ? $events->dispatch($event, $payload)
            : $events->fire($event, $payload);
    }

    /**
     * Fire an event until it is halted.
     *
     * @param string|\ArtisanSdk\Contract\Event
     * @param  array  $payload
     * @return array|null
     */
    public function until($event, $payload = [])
    {
        return $this->makeEvents()->until($event, $payload);
    }

    /**
     * Wrap the class with an argument builder.
     *
     * @return Builder
     */
    protected function newBuilder($class)
    {
        return new Builder($class);
    }

    /**
     * Make the events service.
     *
     * @return \Illuminate\Events\Dispatcher
     */
    protected function makeEvents()
    {
        return $this->container->make(Events::class);
    }

    /**
     * Make a class from the container.
     *
     * @param  string  $class
     * @return mixed
     */
    protected function makeFromContainer($class)
    {
        return $this->container->make($class);
    }

    /**
     * Resolve a runnable class.
     *
     * @param  string|Runnable  $class
     * @return Runnable
     */
    protected function resolveClass($class)
    {
        return is_string($class) ? $this->makeFromContainer($class) : $class;
    }

    /**
     * Provides an event based on the action taking place.
     *
     * If no event exists for the given class and the action, the default is returned.
     *
     * @example resolveEventClass(\ArtisanSdk\Model\Bar\Model, \ArtisanSdk\CQR\Events\Creating) ==> \ArtisanSdk\CQR\Events\Bar\Creating
     *          resolveEventClass(\ArtisanSdk\Model\Bar, \ArtisanSdk\CQR\Events\Creating) ==> \ArtisanSdk\CQR\Events\Bar\Creating
     *          resolveEventClass(\ArtisanSdk\Command\Bar, \ArtisanSdk\CQR\Events\Running) ==> \ArtisanSdk\CQR\Events\Bar\Running
     *          resolveEventClass(\ArtisanSdk\Query\Bar, \ArtisanSdk\CQR\Events\Querying) ==> \ArtisanSdk\CQR\Events\Bar\Querying
     *
     * @param  string  $class
     * @param  string  $default
     * @return string
     */
    protected function resolveEventClass($class, $default)
    {
        $event = $this->normalizeEventClass((string) $class, $default);

        if (class_exists($event)) {
            return $event;
        }

        $fallback = str_replace(class_basename($class).'\\', '', $event);
        if (class_exists($fallback)) {
            return $fallback;
        }

        if (class_exists($default)) {
            return $default;
        }

        return Event::class;
    }

    /**
     * Find the positional occurrence of the first needle in the haystack.
     *
     * @param  string  $haystack
     * @param  string|array  $needles
     * @return int
     */
    protected function findOccurence($haystack, $needles): int
    {
        $position = strlen($haystack);

        foreach ((array) $needles as $needle) {
            $position = stripos($haystack, $needle);
            if ($position !== false) {
                break;
            }
        }

        return (int) $position;
    }

    /**
     * Get the normalized event class name.
     *
     * @param  string  $class
     * @param  string  $default
     * @return string
     */
    protected function normalizeEventClass($class, $default)
    {
        $action = class_basename($default);

        $normalized = rtrim(preg_replace('/(Model|Command|Query|Event|'.$action.')$/', '', $class), '\\');

        return str_replace(['\\Models\\', '\\Commands\\', '\\Queries\\'], '\\Events\\', $normalized).'\\'.$action;
    }
}
