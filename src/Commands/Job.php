<?php

namespace ArtisanSdk\CQRS\Commands;

use ArtisanSdk\Contract\Event;
use ArtisanSdk\Contract\Handler;
use ArtisanSdk\Contract\Runnable;
use ArtisanSdk\CQRS\Traits\Queues;
use ArtisanSdk\Model\Exceptions\InvalidModel; // @todo
use Exception;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log; // @todo inject
use RuntimeException;

class Job implements ShouldQueue
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
     * Construct the job arguments.
     *
     * @param \ArtisanSdk\Contract\Event                $event
     * @param string|array|\ArtisanSdk\Contract\Handler $handler
     */
    public function __construct(Event $event, $handler)
    {
        $this->event = $event;
        $this->handler = $this->resolveHandler($handler);
        if (is_object($this->handler)) {
            $this->copyQueueSettingsFromHandler($handler);
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
                return $this->run($this->handler, $this->event);
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
        return Dispatcher::make()
            ->command($handler)
            ->arguments($event->properties())
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
        return app($class)->$handler($this->event); // @todo remove dep on app()
    }

    /**
     * Process an exception that caused the job to fail.
     *
     * @param \Throwable $exception
     */
    public function failed(Exception $exception)
    {
        Log::error($this->getHandlerSignature($this->handler).': '.$exception->getMessage()); // @todo use injected logger if logging enabled

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
     * @return string
     */
    protected function resolveHandler($handler)
    {
        if ($handler instanceof Handler) {
            return get_class($handler);
        }

        if (is_array($handler)) {
            return $handler;
        }

        if (str_contains($handler, '@')) {
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
}
