<?php

namespace ArtisanSdk\CQRS;

use ArtisanSdk\Contract\Event;
use ArtisanSdk\Contract\Eventable;
use ArtisanSdk\Contract\Query;
use ArtisanSdk\Contract\Runnable;
use ArtisanSdk\Contract\Transactional;
use InvalidArgumentException;

class Dispatcher
{
    /**
     * Make an instance of the dispatcher.
     *
     * @return \ArtisanSdk\CQRS\Dispatcher
     */
    public static function make()
    {
        return app(static::class);
    }

    /**
     * Dynamically forward methods as events.
     *
     * @example creating(new \ArtisanSdk\Model\Bar) => \ArtisanSdk\Event\Bar\Creating
     *          created(new \ArtisanSdk\Model\Bar) => \ArtisanSdk\Event\Bar\Created
     *          updating(new \ArtisanSdk\Model\Bar) => \ArtisanSdk\Event\Bar\Updating
     *          updated(new \ArtisanSdk\Model\Bar) => \ArtisanSdk\Event\Bar\Updated
     *          deleting(new \ArtisanSdk\Model\Bar) => \ArtisanSdk\Event\Bar\Deleting
     *          deleted(new \ArtisanSdk\Model\Bar) => \ArtisanSdk\Event\Bar\Deleted
     *          finding(new \ArtisanSdk\Model\Bar) => \ArtisanSdk\Event\Bar\Finding
     *          found(new \ArtisanSdk\Model\Bar) => \ArtisanSdk\Event\Bar\Found
     *          running(new \ArtisanSdk\Command\Bar) => \ArtisanSdk\Event\Bar\Running
     *          ran(new \ArtisanSdk\Command\Bar) => \ArtisanSdk\Event\Bar\Ran
     *          querying(new \ArtisanSdk\Query\Bar) => \ArtisanSdk\Event\Bar\Querying
     *          queried(new \ArtisanSdk\Query\Bar) => \ArtisanSdk\Event\Bar\Queried
     *
     * @param string $method
     * @param array  $attributes
     *
     * @return mixed
     */
    public function __call($method, $attributes = [])
    {
        $class = head($attributes);
        $default = str_replace('Commands\\'.class_basename($this), 'Events\\'.studly_case($method), get_class($this));
        $classname = is_object($class) ? get_class($class) : $class;
        $name = $this->resolveEventClass($classname, $default);

        $fire = ends_with($method, 'ing') ? 'until' : 'event';

        $event = (new $name(...$attributes))->event($this->normalizeEventClass($classname, $default));

        $this->{$fire}($event);
    }

    /**
     * Dispatch a runnable command or query.
     *
     * @param string|\ArtisanSdk\Contract\Runnable $class
     *
     * @throws \InvalidArugmentException if class argument is not an instance of \ArtisanSdk\Contract\Runnable
     *
     * @return \ArtisanSdk\Contract\Runnable
     */
    public function dispatch($class)
    {
        $runnable = is_string($class) ? app($class) : $class;

        if ($runnable instanceof Command) {
            return $this->command($runnable);
        }

        if ($runnable instanceof Query) {
            return $this->query($runnable);
        }

        if ($runnable instanceof Runnable) {
            return $runnable;
        }

        throw new InvalidArgumentException(get_class($class).' must be an instance of '.Runnable::class);
    }

    /**
     * Instantiate a command so it can be ran.
     *
     * @param string|\ArtisanSdk\Contract\Runnable $class
     *
     * @throws \InvalidArugmentException if class argument is not an instance of \ArtisanSdk\Command\Command
     *
     * @return \ArtisanSdk\Contract\Runnable
     */
    public function command($class)
    {
        $command = is_string($class) ? app($class) : $class;

        if ($command instanceof Transactional) {
            return $this->argumented(new Transaction($command));
        }

        if ($command instanceof Eventable) {
            return $this->argumented(new Evented($command));
        }

        if ($command instanceof Command) {
            return $this->argumented($command);
        }

        throw new InvalidArgumentException(get_class($command).' must be an instance of '.Command::class);
    }

    /**
     * Instantiate a query so it can be ran.
     *
     * @param string|\ArtisanSdk\Contract\Query $class
     *
     * @throws \InvalidArugmentException if class argument is not an instance of \ArtisanSdk\Contract\Query
     *
     * @return \ArtisanSdk\Contract\Query
     */
    public function query($class)
    {
        $query = is_string($class) ? app($class) : $class;

        if ($query instanceof Eventable) {
            return $this->argumented(new Evented($query));
        }

        if ($query instanceof Query) {
            return $this->argumented($query);
        }

        throw new InvalidArgumentException(get_class($query).' must be an instance of '.Query::class);
    }

    /**
     * Fire an event.
     *
     * @param $event
     * @param array $payload
     */
    public function event($event, $payload = [])
    {
        app('events')->fire($event, $payload);
    }

    /**
     * Fire an event until it is halted.
     *
     * @param $event
     * @param array $payload
     */
    public function until($event, $payload = [])
    {
        app('events')->until($event, $payload);
    }

    /**
     * Provides an event based on the action taking place.
     *
     * If no event exists for the given class and the action, the default is returned.
     *
     * @example resolveEventClass(\ArtisanSdk\Model\Bar\Model, \ArtisanSdk\Event\Creating) ==> \ArtisanSdk\Event\Bar\Creating
     *          resolveEventClass(\ArtisanSdk\Model\Bar, \ArtisanSdk\Event\Creating) ==> \ArtisanSdk\Event\Bar\Creating
     *          resolveEventClass(\ArtisanSdk\Command\Bar, \ArtisanSdk\Event\Running) ==> \ArtisanSdk\Event\Bar\Running
     *          resolveEventClass(\ArtisanSdk\Query\Bar, \ArtisanSdk\Event\Querying) ==> \ArtisanSdk\Event\Bar\Querying
     *
     * @param string $class
     * @param string $default
     *
     * @return string
     */
    protected function resolveEventClass($class, $default)
    {
        $event = $this->normalizeEventClass($class, $default);

        if (class_exists($event)) {
            return $event;
        }

        if (class_exists($default)) {
            return $default;
        }

        return Event::class;
    }

    /**
     * Get the normalized event class name.
     *
     * @param string $class
     * @param string $default
     *
     * @return string
     */
    protected function normalizeEventClass($class, $default)
    {
        $normalized = rtrim(preg_replace('/(Model|Command|Query)$/', '', $class), '\\');

        $action = class_basename($default);

        $event = str_replace(['\\Models\\', '\\Commands\\', '\\Queries\\'], '\\Events\\', $normalized).'\\'.$action;

        return str_replace(class_basename($class).'\\', '', $event);
    }

    /**
     * Wrap the class with an argument builder.
     *
     * @return \ArtisanSdk\CQRS\Builder
     */
    protected function argumented($class)
    {
        return new Builder($class);
    }
}
