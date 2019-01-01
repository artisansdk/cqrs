<?php

namespace ArtisanSdk\CQRS\Commands;

use ArtisanSdk\Contract\Command as Contract;
use ArtisanSdk\CQRS\Dispatcher;
use ArtisanSdk\CQRS\Traits\Arguments;
use ArtisanSdk\CQRS\Traits\CQRS;
use ArtisanSdk\CQRS\Traits\Handle;
use ArtisanSdk\CQRS\Traits\Save;
use ArtisanSdk\CQRS\Traits\Silencer;

/**
 * Command Base Class.
 *
 * @example $result = Command::make($arguments)->run()
 *          $result = Command::make()->foo('bar')->run()
 *          $result = Command::make()->silently()
 */
abstract class Command implements Contract
{
    use Arguments, CQRS, Handle, Save, Silencer;

    /**
     * The abort status of the command.
     *
     * @var bool
     */
    protected $aborted = false;

    /**
     * Create new instance of command.
     *
     * @param array $arguments
     *
     * @return \ArtisanSdk\Contract\Command
     */
    public static function make(array $arguments = [])
    {
        return Dispatcher::make()->command(static::class)->arguments($arguments);
    }

    /**
     * Abort the command with a response.
     *
     * @param mixed $response
     *
     * @return self
     */
    public function abort()
    {
        $this->aborted = true;

        return $this;
    }

    /**
     * Was the command aborted?
     *
     * @return bool
     */
    public function aborted()
    {
        return $this->aborted;
    }

    /**
     * Run the command.
     *
     * @return mixed
     */
    abstract public function run();

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
