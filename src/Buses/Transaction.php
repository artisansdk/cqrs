<?php

declare(strict_types=1);

namespace ArtisanSdk\CQRS\Buses;

use ArtisanSdk\Contract\{Command as Contract, Invokable, Runnable};
use ArtisanSdk\CQRS\Concerns\Handle;
use ArtisanSdk\CQRS\Dispatcher;
use Exception;
use Illuminate\Database\ConnectionInterface as Database;

/**
 * Transactional Wrapper.
 */
class Transaction implements Contract
{
    use Handle;

    /**
     * The underlying runnable this class proxies to.
     *
     * @var Runnable
     */
    protected $runnable;

    /**
     * The database connection.
     *
     * @var Database
     */
    protected $database;

    /**
     * Inject the underlying command that this class proxies to.
     *
     * @param  Runnable  $runnable
     * @param  Dispatcher  $dispatcher
     * @param  Database  $database
     */
    public function __construct(Runnable $runnable, ?Dispatcher $dispatcher = null, ?Database $database = null)
    {
        $this->runnable = $runnable;
        $this->database = $database;
    }

    /**
     * Get the base most runnable.
     *
     * @return Invokable
     */
    public function toBase(): Invokable
    {
        return $this->runnable->toBase();
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

        $runnable = $this->toBase();

        if (method_exists($runnable, 'aborted') && $runnable->aborted()) {
            $this->database->rollback();

            return $response;
        }

        $this->database->commit();

        return $response;
    }

    /**
     * Proxy calls to the underlying command instance.
     *
     * @param  string  $method
     * @param  array  $arguments
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
