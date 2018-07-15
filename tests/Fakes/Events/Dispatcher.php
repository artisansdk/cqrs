<?php

namespace ArtisanSdk\CQRS\Tests\Fakes\Events;

use Illuminate\Contracts\Events\Dispatcher as DispatcherInterface;

class Dispatcher implements DispatcherInterface
{
    /**
     * The events store.
     *
     * @var array
     */
    protected $events = [];

    /**
     * Register an event listener with the dispatcher.
     *
     * @param string|array $events
     * @param mixed        $listener
     */
    public function listen($events, $listener)
    {
    }

    /**
     * Determine if a given event has listeners.
     *
     * @param string $eventName
     *
     * @return bool
     */
    public function hasListeners($eventName)
    {
    }

    /**
     * Register an event subscriber with the dispatcher.
     *
     * @param object|string $subscriber
     */
    public function subscribe($subscriber)
    {
    }

    /**
     * Dispatch an event until the first non-null response is returned.
     *
     * @param string|object $event
     * @param mixed         $payload
     *
     * @return array|null
     */
    public function until($event, $payload = [])
    {
        return $this->dispatch($event, $payload, true);
    }

    /**
     * Dispatch an event and call the listeners.
     *
     * @param string|object $event
     * @param mixed         $payload
     * @param bool          $halt
     *
     * @return array|null
     */
    public function dispatch($event, $payload = [], $halt = false)
    {
        $name = is_string($event) ? $event : get_class($event);

        if ( ! isset($this->events[$name])) {
            $this->events[$name] = [];
        }

        $this->events[$name][] = [
            'name'    => $name,
            'class'   => $event,
            'payload' => $payload,
            'halt'    => $halt,
        ];

        return $this->events[$name];
    }

    /**
     * Register an event and payload to be fired later.
     *
     * @param string $event
     * @param array  $payload
     */
    public function push($event, $payload = [])
    {
    }

    /**
     * Flush a set of pushed events.
     *
     * @param string $event
     */
    public function flush($event)
    {
    }

    /**
     * Remove a set of listeners from the dispatcher.
     *
     * @param string $event
     */
    public function forget($event)
    {
    }

    /**
     * Forget all of the queued listeners.
     */
    public function forgetPushed()
    {
    }
}
