<?php

namespace ArtisanSdk\CQRS\Tests\Fakes\Log;

use Psr\Log\LoggerInterface;
use Stringable;

class Logger implements LoggerInterface
{
    /**
     * The logs in memory.
     *
     * @var array
     */
    public $logs = [];

    /**
     * System is unusable.
     *
     * @param \Stringable|string $message
     * @param array  $context
     */
    public function emergency(Stringable|string $message, array $context = []) : void
    {
        $this->log(__FUNCTION__, $message, $context);
    }

    /**
     * Action must be taken immediately.
     *
     * Example: Entire website down, database unavailable, etc. This should
     * trigger the SMS alerts and wake you up.
     *
     * @param \Stringable|string $message
     * @param array  $context
     */
    public function alert(Stringable|string $message, array $context = []) : void
    {
        $this->log(__FUNCTION__, $message, $context);
    }

    /**
     * Critical conditions.
     *
     * Example: Application component unavailable, unexpected exception.
     *
     * @param \Stringable|string $message
     * @param array  $context
     */
    public function critical(Stringable|string $message, array $context = []) : void
    {
        $this->log(__FUNCTION__, $message, $context);
    }

    /**
     * Runtime errors that do not require immediate action but should typically
     * be logged and monitored.
     *
     * @param \Stringable|string $message
     * @param array  $context
     */
    public function error(Stringable|string $message, array $context = []) : void
    {
        $this->log(__FUNCTION__, $message, $context);
    }

    /**
     * Exceptional occurrences that are not errors.
     *
     * Example: Use of deprecated APIs, poor use of an API, undesirable things
     * that are not necessarily wrong.
     *
     * @param \Stringable|string $message
     * @param array  $context
     */
    public function warning(Stringable|string $message, array $context = []) : void
    {
        $this->log(__FUNCTION__, $message, $context);
    }

    /**
     * Normal but significant events.
     *
     * @param \Stringable|string $message
     * @param array  $context
     */
    public function notice(Stringable|string $message, array $context = []) : void
    {
        $this->log(__FUNCTION__, $message, $context);
    }

    /**
     * Interesting events.
     *
     * Example: User logs in, SQL logs.
     *
     * @param \Stringable|string $message
     * @param array  $context
     */
    public function info(Stringable|string $message, array $context = []) : void
    {
        $this->log(__FUNCTION__, $message, $context);
    }

    /**
     * Detailed debug information.
     *
     * @param \Stringable|string $message
     * @param array  $context
     */
    public function debug(Stringable|string $message, array $context = []) : void
    {
        $this->log(__FUNCTION__, $message, $context);
    }

    /**
     * Logs with an arbitrary level.
     *
     * @param mixed  $level
     * @param \Stringable|string $message
     * @param array  $context
     */
    public function log($level, $message, array $context = []) : void
    {
        $this->logs[$level][] = $message;
    }
}
