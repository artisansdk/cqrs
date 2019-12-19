<?php

namespace ArtisanSdk\CQRS\Concerns;

use Illuminate\Contracts\Support\Arrayable;

trait Arguments
{
    use Validation;

    /**
     * The arguments and options for the command.
     *
     * @var array
     */
    protected $arguments = [];

    /**
     * Get or set the arguments and options for the command.
     *
     * @param array|\Illuminate\Contracts\Support\Arrayable|null $arguments
     *
     * @return self|array
     */
    public function arguments($arguments = null)
    {
        if (is_null($arguments)) {
            return $this->arguments;
        }

        $this->arguments = $arguments instanceof Arrayable
            ? $arguments->toArray()
            : (array) $arguments;

        return $this;
    }

    /**
     * Get the argument by key name and optionally validate the value.
     *
     * @param string $name
     * @param mixed  $validator
     *
     * @return mixed
     */
    public function argument($name, $validator = null)
    {
        $value = $this->option($name);

        if (is_null($value)) {
            $this->invalidArgument(
                'Argument "%s" is required by %s.',
                $name,
                get_class($this)
            );
        }

        if ($validator) {
            return $this->validateValue($name, $value, $validator);
        }

        return $value;
    }

    /**
     * Get the option by key name or provide a default.
     *
     * @param string $name
     * @param mixed  $default
     * @param mixed  $validator
     *
     * @return mixed
     */
    public function option(string $name, $default = null, $validator = null)
    {
        $value = $this->hasOption($name)
            ? array_get($this->arguments, $name)
            : $this->resolveDefault($name, $default);

        if (is_string($value) && '' === $value) {
            $value = $this->resolveDefault($name, $default);
        }

        if ( ! is_null($value) && $validator) {
            return $this->validateValue($name, $value, $validator);
        }

        return $value;
    }

    /**
     * Does the class have the option set?
     *
     * @param string $name
     *
     * @return bool
     */
    protected function hasOption(string $name): bool
    {
        return ! is_null(array_get($this->arguments, $name));
    }

    /**
     * Does the class have all given options set?
     * 
     * @param array $names
     * 
     * @return bool
     */
    protected function hasOptions(array $names): bool
    {
        foreach ($names as $name) {
            if (! $this->hasOption($name)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Resolve the default value from a primitive or a closure.
     *
     * @param string $name  of option
     * @param mixed  $value
     *
     * @return mixed
     */
    protected function resolveDefault(string $name, $value)
    {
        if (is_callable($value)) {
            return $value($name);
        }

        return $value;
    }
}
