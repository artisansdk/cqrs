<?php

namespace ArtisanSdk\CQRS\Traits;

use Illuminate\Http\Request;

trait Arguments
{
    /**
     * The arguments for the command.
     *
     * @var array
     */
    protected $arguments = [];

    /**
     * Get or set the arguments for the command.
     *
     * @todo allow request to be passed as an argument and have command abstract the needed arguments
     *
     * @param array|null|\Illuminate\Http\Request $arguments
     *
     * @return self|array
     */
    public function arguments($arguments = null)
    {
        if ( ! is_null($arguments)) {
            $this->arguments = $arguments instanceof Request ? $arguments->all() : (array) $arguments;

            return $this;
        }

        return $this->arguments;
    }

    /**
     * Get the argument by key name or provide a default.
     *
     * @param string $name
     * @param mixed  $default
     *
     * @return mixed
     */
    public function argument($name, $default = null)
    {
        $value = array_has($this->arguments, $name)
            ? array_get($this->arguments, $name)
            : $this->resolveDefault($default);

        if (is_string($value) && '' === $value) {
            return $this->resolveDefault($default);
        }

        return $value;
    }

    /**
     * Resolve the default value from a primitive or a closure.
     *
     * @param mixed $value
     *
     * @return mixed
     */
    protected function resolveDefault($value)
    {
        if ( ! is_callable($value)) {
            return $value;
        }

        return $value();
    }
}
