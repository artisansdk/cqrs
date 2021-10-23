<?php

namespace ArtisanSdk\CQRS\Tests\Fakes\Jobs;

use Illuminate\Contracts\Queue\Job as Contract;

class Job implements Contract
{
    /**
     * Indicates if the job has been deleted.
     *
     * @var bool
     */
    protected $deleted = false;

    /**
     * Indicates if the job has been released.
     *
     * @var bool
     */
    protected $released = false;
    /**
     * Indicates if the job has failed.
     *
     * @var bool
     */
    protected $failed = false;

    /**
     * Get the UUID of the job.
     *
     * @return string|null
     */
    public function uuid()
    {
    }

    /**
     * Get the job identifier.
     *
     * @return string
     */
    public function getJobId()
    {
    }

    /**
     * Get the decoded body of the job.
     *
     * @return array
     */
    public function payload()
    {
    }

    /**
     * Fire the job.
     */
    public function fire()
    {
    }

    /**
     * Release the job back into the queue.
     *
     * @param int $delay
     *
     * @return mixed
     */
    public function release($delay = 0)
    {
        $this->released = true;
    }

    /**
     * Determine if the job was released back into the queue.
     *
     * @return bool
     */
    public function isReleased()
    {
        return $this->released;
    }

    /**
     * Delete the job from the queue.
     */
    public function delete()
    {
        $this->deleted = true;
    }

    /**
     * Determine if the job has been deleted.
     *
     * @return bool
     */
    public function isDeleted()
    {
        return $this->deleted;
    }

    /**
     * Determine if the job has been deleted or released.
     *
     * @return bool
     */
    public function isDeletedOrReleased()
    {
        return $this->isDeleted() || $this->isReleased();
    }

    /**
     * Determine if the job has been marked as a failure.
     *
     * @return bool
     */
    public function hasFailed()
    {
        return $this->failed;
    }

    /**
     * Mark the job as "failed".
     */
    public function markAsFailed()
    {
        $this->failed = true;
    }

    /**
     * Delete the job, call the "failed" method, and raise the failed job event.
     *
     * @param \Throwable|null $e
     */
    public function fail($e = null)
    {
        $this->markAsFailed();
    }

    /**
     * Get the number of times the job has been attempted.
     *
     * @return int
     */
    public function attempts()
    {
    }

    /**
     * Process an exception that caused the job to fail.
     *
     * @param \Throwable $e
     */
    public function failed($e)
    {
    }

    /**
     * Get the number of times to attempt a job.
     *
     * @return int|null
     */
    public function maxTries()
    {
    }

    /**
     * Get the maximum number of exceptions allowed, regardless of attempts.
     *
     * @return int|null
     */
    public function maxExceptions()
    {
    }

    /**
     * Get the number of seconds the job can run.
     *
     * @return int|null
     */
    public function timeout()
    {
    }

    /**
     * Get the timestamp indicating when the job should timeout.
     *
     * @return int|null
     */
    public function retryUntil()
    {
    }

    /**
     * Get the timestamp indicating when the job should timeout.
     *
     * @return int|null
     */
    public function timeoutAt()
    {
    }

    /**
     * Get the name of the queued job class.
     *
     * @return string
     */
    public function getName()
    {
    }

    /**
     * Get the resolved name of the queued job class.
     *
     * Resolves the name of "wrapped" jobs such as class-based handlers.
     *
     * @return string
     */
    public function resolveName()
    {
    }

    /**
     * Get the name of the connection the job belongs to.
     *
     * @return string
     */
    public function getConnectionName()
    {
    }

    /**
     * Get the name of the queue the job belongs to.
     *
     * @return string
     */
    public function getQueue()
    {
    }

    /**
     * Get the raw body string for the job.
     *
     * @return string
     */
    public function getRawBody()
    {
    }
}
