<?php

namespace ArtisanSdk\CQRS\Jobs;

use ArtisanSdk\Contract\Event;
use ArtisanSdk\Contract\Handler;
use ArtisanSdk\Contract\Runnable;
use ArtisanSdk\CQRS\Dispatcher;
use ArtisanSdk\CQRS\Traits\Queues;
use ArtisanSdk\Model\Exceptions\InvalidModel; // @todo
use Exception;
use Illuminate\Contracts\Queue\ShouldQueue;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;

class Job implements ShouldQueue, LoggerAwareInterface
{
    use Queues;

    /**
     * The event that contains the queued handler's arguments.
     *
     * @var \ArtisanSdk\Contract\Event
     */
    protected $event;

    /**
     * The handler that this queued job is executed through.
     *
     * @var \ArtisanSdk\Contract\Handler
     */
    protected $handler;

    /**
     * The logger interface.
     *
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;

    /**
     * Construct the job arguments.
     *
     * @param \ArtisanSdk\Contract\Event                $event
     * @param string|array|\ArtisanSdk\Contract\Handler $handler
     * @param \Psr\Log\LoggerInterface                  $logger
     */
    public function __construct(Event $event, $handler, LoggerInterface $logger = null)
    {
        $this->event = $event;
        $this->handler = $this->resolveHandler($handler);
        if (is_object($handler)) {
            $this->copyQueueSettingsFromHandler($handler);
        }
        if ( ! is_null($logger)) {
            $this->setLogger($logger);
        }
    }

    /**
     * Handle the queued job by dispatching the queued event to the queued handler.
     *
     * @return mixed|void
     */
    public function handle()
    {
        try {
            if ($this->isRunnable($this->handler)) {
                return $this->run(is_array($this->handler) ? head($this->handler) : $this->handler, $this->event);
            }

            if (is_array($this->handler)) {
                return $this->call(head($this->handler), end($this->handler), $this->event);
            }

            return $this->call($this->handler, 'handle', $this->event);
        } catch (Exception $exception) {
            $this->failed($exception);
        }
    }

    /**
     * Run the command.
     *
     * @param string                     $handler
     * @param \ArtisanSdk\Contract\Event $event
     *
     * @return mixed
     */
    public function run($handler, Event $event)
    {
        $properties = $event->properties();

        return Dispatcher::make()
            ->command($handler)
            ->arguments($properties['payload'])
            ->run();
    }

    /**
     * Call the handler method.
     *
     * @param string                     $class
     * @param string                     $handler
     * @param \ArtisanSdk\Contract\Event $event
     *
     * @return mixed
     */
    public function call($class, $handler, Event $event)
    {
        $class = is_string($class) ? app($class) : $class; // @todo remove dep on app()

        return $class->$handler($this->event);
    }

    /**
     * Process an exception that caused the job to fail.
     *
     * @param \Throwable $exception
     */
    public function failed(Exception $exception)
    {
        $this->log($exception);

        if ($exception instanceof RuntimeException || $exception instanceof InvalidModel) {
            return $this->delete();
        }

        return $this->fail($exception);
    }

    /**
     * Determine if handler is a runnable.
     *
     * @param string|array $handler
     *
     * @return bool
     */
    protected function isRunnable($handler)
    {
        return (is_string($handler) && is_subclass_of($handler, Runnable::class))
            || (is_array($handler) && 'run' === end($handler));
    }

    /**
     * Resolve the handler for this job.
     *
     * @param string|array|\ArtisanSdk\Contract\Handler $handler
     *
     * @return array|object
     */
    protected function resolveHandler($handler)
    {
        if ($handler instanceof Handler) {
            return get_class($handler);
        }

        if (is_array($handler)) {
            return $handler;
        }

        if (is_string($handler) && str_contains($handler, '@')) {
            return explode('@', $handler);
        }

        return $handler;
    }

    /**
     * Copy the queue settings from the handler on to this job.
     *
     * @param stdClass $handler
     */
    protected function copyQueueSettingsFromHandler($handler)
    {
        if (isset($handler->queue)) {
            $this->onQueue($handler->queue);
        }
        if (isset($handler->connection)) {
            $this->onConnection($handler->connection);
        }
        if (isset($handler->delay)) {
            $this->delay($handler->delay);
        }
    }

    /**
     * Get the handler signature name.
     *
     * @param string|array $handler
     *
     * @return string
     */
    protected function getHandlerSignature($handler)
    {
        if (is_array($handler)) {
            return implode('@', $handler);
        }

        return $handler;
    }

    /**
     * Log the exception as an error if the logger is present.
     *
     * @param \Exception $exception
     */
    protected function log(Exception $exception)
    {
        if ($logger = $this->logger()) {
            $logger = $logger->error(sprintf('%s: %s', $this->getHandlerSignature($this->handler), $exception->getMessage()));
        }
    }

    /**
     * Get or set the logger.
     *
     * @param \Psr\Log\LoggerInterface $logger
     *
     * @return \Psr\Log\LoggerInterface
     */
    public function logger(LoggerInterface $logger = null)
    {
        if ( ! is_null($logger)) {
            $this->setLogger($logger);

            return $this;
        }

        return $this->logger;
    }

    /**
     * Set a logger instance on the object.
     *
     * @param \Psr\Log\LoggerInterface $logger
     */
    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }
}
