<?php

namespace ArtisanSdk\CQRS\Concerns;

use InvalidArgumentException;

trait Validation
{
    /**
     * Validate the argument value by key name.
     *
     * @param string $name      of argument
     * @param mixed  $value     of argument
     * @param mixed  $validator
     *
     * @throws \InvalidArgumentException
     *
     * @return mixed
     */
    protected function validateValue(string $name, $value, $validator)
    {
        if ($this->validatorIsFunction($validator)) {
            return $this->validateWithFunction($name, $value, $validator);
        }

        if ($this->validatorIsCallable($validator)) {
            return $this->validateWithCallable($name, $value, $validator);
        }

        if ($this->validatorIsRulesArray($validator)) {
            return $this->validateWithRules($name, $value, $validator);
        }

        if ($this->validatorValidates($validator)) {
            $validator->validate();

            return $value;
        }

        if ( ! $this->valueIsValidClass($value, $validator)) {
            $this->invalidArgument(
                'The "%s" argument validator must be a class or interface name, a callable, or an instance of a Validator.',
                $name
            );
        }

        return $value;
    }

    /**
     * Is validator a rules array?
     *
     * @param array $validator
     *
     * @return bool
     */
    protected function validatorIsRulesArray($validator): bool
    {
        return is_array($validator);
    }

    /**
     * Is validator a function reference?
     *
     * @param mixed $validator
     *
     * @return bool
     */
    protected function validatorIsFunction($validator): bool
    {
        return is_string($validator)
            && function_exists($validator);
    }

    /**
     * Is validator a callable?
     *
     * @param mixed $validator
     *
     * @return bool
     */
    protected function validatorIsCallable($validator): bool
    {
        return is_callable($validator);
    }

    /**
     * Does validator have a validate method?
     *
     * @param object $validator
     *
     * @return bool
     */
    protected function validatorValidates($validator): bool
    {
        return method_exists($validator, 'validate');
    }

    /**
     * Is value a valid class.
     *
     * @param object $value
     * @param string $class
     *
     * @return bool
     */
    protected function valueIsValidClass($value, $class): bool
    {
        return is_object($value)
            && is_string($class)
            && get_class($value) === $class;
    }

    /**
     * Validate the value with the function.
     *
     * @param string   $name
     * @param mixed    $value
     * @param function $validator
     *
     * @return mixed
     */
    protected function validateWithFunction(string $name, $value, $validator)
    {
        if ( ! $validator($value)) {
            $this->invalidArgument(
                'The value for the "%s" argument could not be validated using %s().',
                $name,
                $validator
            );
        }

        return $value;
    }

    /**
     * Validate the value with the callable.
     *
     * @param string   $name
     * @param mixed    $value
     * @param callable $validator
     *
     * @return mixed
     */
    protected function validateWithCallable(string $name, $value, $validator)
    {
        if ( ! $validator($value, $name)) {
            $this->invalidArgument(
                'The value for the "%s" argument could not be validated using the callable.',
                $name,
                $validator
            );
        }

        return $value;
    }

    /**
     * Validate the value with rules.
     *
     * @param string $name
     * @param mixed  $value
     * @param array  $rules
     *
     * @return mixed
     */
    protected function validateWithRules(string $name, $value, array $rules)
    {
        $this->makeValidator($name, $value, $rules)->validate();

        return $value;
    }

    /**
     * Make a validator out of the rules.
     *
     * @param string $name
     * @param mixed  $value
     * @param array  $rules
     *
     * @return \Illuminate\Contracts\Validation\Validator
     */
    public static function makeValidator(string $name, $value, array $rules)
    {
        return app('validator')
            ->make(
                [$name => $value],
                [$name => $rules]
            );
    }

    /**
     * Throw an invalid argument exception.
     *
     * @param string   $message
     * @param string[] $replacements
     *
     * @throws \InvalidArgumentException
     */
    protected function invalidArgument(string $message, ...$replacements)
    {
        throw new InvalidArgumentException(sprintf($message, ...$replacements));
    }
}
