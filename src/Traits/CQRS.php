<?php

namespace ArtisanSdk\CQRS\Traits;

use ArtisanSdk\CQRS\Dispatcher;

trait CQRS
{
    /**
     * Make an instance of the dispatcher.
     *
     * @return \ArtisanSdk\CQRS\Dispatcher
     */
    protected function dispatcher()
    {
        return Dispatcher::make();
    }

    /**
     * Dispatch a runnable command or query.
     *
     * @param string|\ArtisanSdk\Contract\Runnable $class
     * @param array                                $arguments
     *
     * @return \ArtisanSdk\Contract\Runnable
     */
    public function call($class, array $arguments = [])
    {
        return $this->dispatcher()->dispatch($class)->arguments($arguments);
    }

    /**
     * Instantiate a command so it can be ran.
     *
     * @param string|\ArtisanSdk\Contract\Runnable $class
     *
     * @return \ArtisanSdk\Contract\Runnable
     */
    protected function command($class)
    {
        return $this->dispatcher()->command($class);
    }

    /**
     * Instantiate a query so it can be ran.
     *
     * @param string|\ArtisanSdk\Contract\Query $class
     *
     * @return \ArtisanSdk\Contract\Query
     */
    protected function query($class)
    {
        return $this->dispatcher()->query($class);
    }

    /**
     * Fire an event.
     *
     * @param string|\ArtisanSdk\Contract\Event $event
     * @param array                             $payload
     */
    protected function event($event, $payload = [])
    {
        return $this->dispatcher()->event($event, $payload);
    }

    /**
     * Fire an event until it is halted.
     *
     * @param string|\ArtisanSdk\Contract\Event $event
     * @param array                             $payload
     */
    protected function until($event, $payload = [])
    {
        return $this->dispatcher()->until($event, $payload);
    }
}
