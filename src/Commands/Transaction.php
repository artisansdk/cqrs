<?php

namespace ArtisanSdk\CQRS\Commands;

use ArtisanSdk\Contract\Command as Contract;
use ArtisanSdk\Contract\Eventable;
use ArtisanSdk\Contract\Runnable;
use ArtisanSdk\CQRS\Dispatcher;
use ArtisanSdk\CQRS\Traits\Handle;
use Exception;
use Illuminate\Support\Facades\DB;

class Transaction implements Contract
{
    use Handle;

    /**
     * The underlying command this class proxies to.
     *
     * @var \ArtisanSdk\Contract\Runnable
     */
    protected $runnable;

    /**
     * Inject the underlying command that this class proxies to.
     *
     * @param \ArtisanSdk\Contract\Runnable $runnable
     * @param \ArtisanSdk\CQRS\Dispatcher   $dispatcher
     */
    public function __construct(Runnable $runnable, Dispatcher $dispatcher = null)
    {
        $this->runnable = $runnable instanceof Eventable ? new Evented($runnable, $dispatcher) : $runnable;
    }

    /**
     * Run the command in a transaction.
     *
     * @return mixed
     */
    public function run()
    {
        DB::beginTransaction();

        try {
            $response = $this->runnable->run();
        } catch (Exception $exception) {
            DB::rollback();
            throw $exception;
        }

        if ( ! method_exists($this->runnable, 'aborted') || ! $this->runnable->aborted()) {
            DB::rollback();

            return $response;
        }

        DB::commit();

        return $response;
    }

    /**
     * Proxy calls to the underlying command instance.
     *
     * @param string $method
     * @param array  $arguments
     *
     * @return mixed
     */
    public function __call($method, $arguments = [])
    {
        $response = call_user_func_array([$this->runnable, $method], $arguments);

        if ($response === $this->runnable) {
            return $this;
        }

        return $response;
    }

    /**
     * Invoke the command.
     *
     * @return mixed
     */
    public function __invoke()
    {
        return $this->run();
    }
}
