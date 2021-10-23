<?php

namespace ArtisanSdk\CQRS\Tests\Fakes;

use Illuminate\Contracts\Bus\QueueingDispatcher;

class Dispatcher implements QueueingDispatcher
{
    /**
     * Attempt to find the batch with the given ID.
     *
     * @param  string  $batchId
     * @return \Illuminate\Bus\Batch|null
     */
    public function findBatch(string $batchId)
    {
    }

    /**
     * Create a new batch of queueable jobs.
     *
     * @param  \Illuminate\Support\Collection|array  $jobs
     * @return \Illuminate\Bus\PendingBatch
     */
    public function batch($jobs)
    {
    }

    /**
     * Dispatch a command to its appropriate handler.
     *
     * @param mixed $command
     *
     * @return mixed
     */
    public function dispatch($command)
    {
    }

    /**
     * Dispatch a command to its appropriate handler in the current process.
     *
     * Queueable jobs will be dispatched to the "sync" queue.
     *
     * @param  mixed  $command
     * @param  mixed  $handler
     * @return mixed
     */
    public function dispatchSync($command, $handler = null)
    {
    }

    /**
     * Dispatch a command to its appropriate handler in the current process.
     *
     * @param mixed $command
     * @param mixed $handler
     *
     * @return mixed
     */
    public function dispatchNow($command, $handler = null)
    {
        return [$command, $handler ?? 'run'];
    }

    /**
     * Dispatch a command to its appropriate handler behind a queue.
     *
     * @param mixed $command
     *
     * @return mixed
     */
    public function dispatchToQueue($command)
    {
    }

    /**
     * Determine if the given command has a handler.
     *
     * @param mixed $command
     *
     * @return bool
     */
    public function hasCommandHandler($command)
    {
    }

    /**
     * Retrieve the handler for a command.
     *
     * @param mixed $command
     *
     * @return bool|mixed
     */
    public function getCommandHandler($command)
    {
    }

    /**
     * Set the pipes commands should be piped through before dispatching.
     *
     * @param array $pipes
     *
     * @return $this
     */
    public function pipeThrough(array $pipes)
    {
    }

    /**
     * Map a command to a handler.
     *
     * @param array $map
     *
     * @return $this
     */
    public function map(array $map)
    {
    }
}
