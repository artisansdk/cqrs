<?php

namespace ArtisanSdk\CQRS\Commands;

use ArtisanSdk\Contract\Command as Contract;
use ArtisanSdk\Contract\Eventable;
use ArtisanSdk\Contract\Runnable;
use ArtisanSdk\CQRS\Dispatcher;
use ArtisanSdk\CQRS\Traits\Handle;
use Exception;
use Illuminate\Database\ConnectionInterface as Database;

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
     * The database connection.
     *
     * @var \Illuminate\Database\ConnectionInterface
     */
    protected $database;

    /**
     * Inject the underlying command that this class proxies to.
     *
     * @param \ArtisanSdk\Contract\Runnable            $runnable
     * @param \ArtisanSdk\CQRS\Dispatcher              $dispatcher
     * @param \Illuminate\Database\ConnectionInterface $database
     */
    public function __construct(Runnable $runnable, Dispatcher $dispatcher = null, Database $database = null)
    {
        $this->runnable = $runnable instanceof Eventable ? new Evented($runnable, $dispatcher) : $runnable;
        $this->database = $database;
    }

    /**
     * Run the command silently.
     *
     * @return mixed
     */
    public function silently()
    {
        return $this->silence()->__invoke();
    }

    /**
     * Run the command in a transaction.
     *
     * @return mixed
     */
    public function run()
    {
        $this->database->beginTransaction();

        try {
            $response = $this->runnable->run();
        } catch (Exception $exception) {
            $this->database->rollback();
            throw $exception;
        }

        if (method_exists($this->runnable, 'aborted') && $this->runnable->aborted()) {
            $this->database->rollback();

            return $response;
        }

        $this->database->commit();

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
