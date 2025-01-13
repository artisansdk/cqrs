<?php

declare(strict_types=1);

namespace ArtisanSdk\CQRS;

use ArtisanSdk\Contract\{Command as Contract, Invokable};
use ArtisanSdk\CQRS\Concerns\{Arguments, CQRS, Handle, Save, Silencer};

/**
 * Command Base Class.
 *
 * @example $result = Command::make($arguments)->run()
 *          $result = Command::make()->foo('bar')->run()
 *          $result = Command::make()->silently()
 */
abstract class Command implements Contract
{
    use Arguments;
    use CQRS;
    use Handle;
    use Save;
    use Silencer;

    /**
     * The abort status of the command.
     *
     * @var bool
     */
    protected $aborted = false;

    /**
     * Create new instance of command.
     *
     * @param  array  $arguments
     * @return Builder<TContract>
     */
    public static function make(array $arguments = [])
    {
        return Dispatcher::make()
            ->command(static::class)
            ->arguments($arguments);
    }

    /**
     * Get the base most runnable.
     *
     * @return Invokable
     */
    public function toBase(): Invokable
    {
        return $this;
    }

    /**
     * Abort the command with a response.
     *
     * @param  mixed  $response
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
