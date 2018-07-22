<?php

namespace ArtisanSdk\CQRS\Jobs;

class Chain
{
    /**
     * The class name of the job being dispatched.
     *
     * @var string
     */
    public $class;

    /**
     * The jobs to be chained.
     *
     * @var array
     */
    public $chain;

    /**
     * Create a new chain instance.
     *
     * @param string $class
     * @param array  $chain
     */
    public function __construct($class, $chain)
    {
        $this->class = $class;
        $this->chain = $chain;
    }

    /**
     * Dispatch the job with the given arguments.
     *
     * @return \ArtisanSdk\CQRS\Jobs\Pending
     */
    public function dispatch()
    {
        return (new Pending(
            new $this->class(...func_get_args())
        ))->chain($this->chain);
    }
}
