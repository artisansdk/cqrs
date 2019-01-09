<?php

namespace ArtisanSdk\CQRS\Traits;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Validation\Validator;
use InvalidArgumentException;

trait Arguments
{
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
     * @throws \InvalidArgumentException
     *
     * @return mixed
     */
    public function argument($name, $validator = null)
    {
        $value = $this->option($name);

        if (is_null($value)) {
            throw new InvalidArgumentException(sprintf(
                'Argument "%s" is required by %s.',
                $name,
                get_class($this)
            ));
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
        return array_has($this->arguments, $name)
            && ! is_null(array_get($this->arguments, $name));
    }

    /**
     * Validate the argument value by key name.
     *
     * @param string $name      of argument
     * @param mixed  $value     of argument
     * @param mixed  $validator
     *
     * @throws \Illuminate\Validation\ValidationException
     * @throws \InvalidArgumentException
     *
     * @return mixed
     */
    protected function validateValue(string $name, $value, $validator)
    {
        if (is_string($validator)
            && function_exists($validator)
        ) {
            if ( ! $validator($value)) {
                throw new InvalidArgumentException(sprintf(
                    'The value for the "%s" argument could not be validated using %s().',
                    $name,
                    $validator
                ));
            }

            return $value;
        }

        if (is_callable($validator)) {
            if ( ! $validator($value, $name)) {
                throw new InvalidArgumentException(sprintf(
                    'The value for the "%s" argument could not be validated using the callable.',
                    $name,
                    $validator
                ));
            }

            return $value;
        }

        if (is_array($validator)) {
            $validator = app('validator')->make(
                [$name  => $value],
                [$name => $validator]
            );
        }

        if ($validator instanceof Validator) {
            $validator->validate();

            return $value;
        }

        if (is_string($validator)
            && is_object($value)
            && get_class($value) === $validator
        ) {
            return $value;
        }

        throw new InvalidArgumentException(sprintf(
            'The "%s" argument validator must be a class or interface name, a callable, or an instance of %s.',
            $name,
            Validator::class
        ));
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
