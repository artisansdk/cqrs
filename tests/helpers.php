<?php

use ArtisanSdk\CQRS\Jobs\Pending;
use Illuminate\Container\Container;

if ( ! function_exists('app')) {
    /**
     * Get the available container instance.
     *
     * @param string $abstract
     * @param array  $parameters
     *
     * @return mixed|\Illuminate\Foundation\Application
     */
    function app($abstract = null, array $parameters = [])
    {
        if (is_null($abstract)) {
            return Container::getInstance();
        }

        return Container::getInstance()->make($abstract, $parameters);
    }
}

if ( ! function_exists('dispatch')) {
    /**
     * Dispatch a job to its appropriate handler.
     *
     * @param mixed $job
     *
     * @return \ArtisanSdk\CQRS\Jobs\Pending
     */
    function dispatch($job)
    {
        return new Pending($job);
    }
}
