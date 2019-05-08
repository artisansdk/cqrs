<?php

namespace ArtisanSdk\CQRS\Concerns;

use ArtisanSdk\CQRS\Jobs\Chain;
use ArtisanSdk\CQRS\Jobs\Pending;
use Illuminate\Contracts\Bus\Dispatcher;

trait Dispatchable
{
    /**
     * Dispatch the job with the given arguments.
     *
     * @return \ArtisanSdk\CQRS\Jobs\Pending
     */
    public static function dispatch()
    {
        return new Pending(new static(...func_get_args()));
    }

    /**
     * Dispatch a command to its appropriate handler in the current process.
     *
     * @return mixed
     */
    public static function dispatchNow()
    {
        return app(Dispatcher::class)->dispatchNow(new static(...func_get_args()));
    }

    /**
     * Set the jobs that should run if this job is successful.
     *
     * @param array $chain
     *
     * @return \ArtisanSdk\CQRS\Jobs\Chain
     */
    public static function withChain($chain)
    {
        return new Chain(get_called_class(), $chain);
    }
}
