<?php

declare(strict_types=1);

namespace ArtisanSdk\CQRS\Tests\Unit\Concerns;

use ArtisanSdk\CQRS\Tests\Fakes\Commands\Command;
use ArtisanSdk\CQRS\Tests\TestCase;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Fluent;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use JsonSerializable;
use stdClass;

class ArgumentsTest extends TestCase
{
    /**
     * Test that the arguments can be set from an arrayable object.
     */
    public function test_arrayble_arguments()
    {
        $arrayable = new Fluent([
            'foo' => 'bar',
        ]);

        $command = new Command;
        $command->arguments($arrayable);

        $this->assertInstanceOf(Arrayable::class, $arrayable, 'The arguments passed should implement '.Arrayable::class.'.');
        $this->assertSame($arrayable->foo, $command->argument('foo'), 'The arrayable value of "bar" for "foo" argument should have been set.');
    }

    /**
     * Test that an argument is required.
     */
    public function test_argument_is_required()
    {
        $command = new Command;
        $command->arguments([
            'bar' => '',
            'baz' => null,
        ]);

        try {
            $value = $command->argument('foo');
        } catch (InvalidArgumentException $exception) {
            $this->assertSame(
                'Argument "foo" is required by '.get_class($command).'.',
                $exception->getMessage(),
                'The "foo" argument should be required and throw an '.InvalidArgumentException::class.' if not set.'
            );
        }

        try {
            $value = $command->argument('bar');
        } catch (InvalidArgumentException $exception) {
            $this->assertSame(
                'Argument "bar" is required by '.get_class($command).'.',
                $exception->getMessage(),
                'Argument "bar" is an empty string and should be evaluated as required but not set.'
            );
        }

        try {
            $value = $command->argument('baz');
        } catch (InvalidArgumentException $exception) {
            $this->assertSame(
                'Argument "baz" is required by '.get_class($command).'.',
                $exception->getMessage(),
                'Argument "baz" is a null value and should be evaluated as required but not set.'
            );
        }
    }

    /**
     * Test that an argument cannot be validated with an unsupported validator.
     */
    public function test_argument_cannot_be_validated_with_unsupported_validator()
    {
        $command = new Command;
        $command->arguments([
            'foo' => 'bar',
        ]);

        try {
            $value = $command->argument('foo', -1);
        } catch (InvalidArgumentException $exception) {
            $this->assertSame(
                'The "foo" argument validator must be a class or interface name, a callable, or an instance of a Validator.',
                $exception->getMessage(),
                'An unsupported validator should throw an '.InvalidArgumentException::class.'.'
            );
        }
    }

    /**
     * Test that an argument can be validated with a callable.
     */
    public function test_argument_is_validated_with_callable()
    {
        $command = new Command;
        $command->arguments([
            'foo' => 'bar',
        ]);

        $value = $command->argument('foo', fn ($value, string $name) => true);

        $this->assertSame('bar', $value, 'The callable validator should have returned true and therefore resolved the value as "bar".');

        try {
            $value = $command->argument('foo', fn ($value, string $name) => false);
        } catch (InvalidArgumentException $exception) {
            $this->assertSame(
                'The value for the "foo" argument could not be validated using the callable.',
                $exception->getMessage(),
                'An argument that fails the validator callable check should throw an '.InvalidArgumentException::class.'.'
            );
        }
    }

    /**
     * Test that an argument can be validated using an array of rules.
     */
    public function test_argument_is_validated_with_rules_array()
    {
        $command = new Command;
        $command->arguments([
            'foo' => 'bar',
        ]);

        $value = $command->argument('foo', ['string', 'size:3']);

        $this->assertSame('bar', $value, 'The rules array validator should have validated and therefore resolved the value as "bar".');

        try {
            $value = $command->argument('foo', ['integer']);
        } catch (ValidationException $exception) {
            $this->assertSame(
                'validation.integer',
                $exception->getMessage(),
                'An argument that fails the validator rules passed should throw a '.ValidationException::class.'.'
            );
        }
    }

    /**
     * Test that an argument can be validated using a validator.
     */
    public function test_argument_is_validated_with_validator()
    {
        $command = new Command;
        $command->arguments([
            'foo' => 'bar',
        ]);

        $value = $command->argument('foo', $command->makeValidator('foo', 'bar', ['string', 'size:3']));

        $this->assertSame('bar', $value, 'The rules array validator should have validated and therefore resolved the value as "bar".');

        try {
            $value = $command->argument('foo', $command->makeValidator('foo', 'bar', ['integer']));
        } catch (ValidationException $exception) {
            $this->assertSame(
                'validation.integer',
                $exception->getMessage(),
                'An argument that fails the validator rules passed should throw a '.ValidationException::class.'.'
            );
        }
    }

    /**
     * Test that an argument can be validated against a class or interface name.
     */
    public function test_argument_validates_against_class_name()
    {
        $command = new Command;
        $command->arguments([
            'foo' => new stdClass,
            'bar' => new class implements JsonSerializable
            {
                public function jsonSerialize(): mixed
                {
                    return [];
                }
            },
        ]);

        $foo = $command->argument('foo', stdClass::class);
        $bar = $command->argument('bar', JsonSerializable::class);

        $this->assertInstanceOf(stdClass::class, $foo, 'The value for the "foo" argument must be an instance of '.stdClass::class.'.');
        $this->assertInstanceOf(JsonSerializable::class, $bar, 'The value for the "bar" argument must be an instance of '.JsonSerializable::class.'.');

        $command->arguments([
            'foo' => 'bar',
        ]);

        try {
            $value = $command->argument('foo', stdClass::class);
        } catch (InvalidArgumentException $exception) {
            $this->assertSame(
                'The "foo" argument validator must be a class or interface name, a callable, or an instance of a Validator.',
                $exception->getMessage(),
                'An unsupported validator should throw an '.InvalidArgumentException::class.'.'
            );
        }
    }

    /**
     * Test that an argument can be validated against type check.
     */
    public function test_argument_validates_against_type_check()
    {
        $command = new Command;
        $command->arguments([
            'foo' => 'bar',
        ]);

        $value = $command->argument('foo', 'is_string');

        $this->assertIsString($value, 'The value for the "foo" argument must be a string.');

        try {
            $value = $command->argument('foo', 'is_int');
        } catch (InvalidArgumentException $exception) {
            $this->assertSame(
                'The value for the "foo" argument could not be validated using is_int().',
                $exception->getMessage(),
                'An unsupported validator should throw an '.InvalidArgumentException::class.'.'
            );
        }
    }

    /**
     * Test that an option can be validated.
     */
    public function test_option_can_be_validated()
    {
        $command = new Command;

        $value = $command->option('foo', 'bar', fn ($value, string $name) => $value === 'bar');

        $this->assertSame('bar', $value, 'The callable validator should have returned true and therefore resolved the value as "bar".');

        try {
            $value = $command->option('foo', null, fn ($value, string $name) => false);
        } catch (InvalidArgumentException $exception) {
            $this->assertSame(
                'The value for the "foo" argument could not be validated using the callable.',
                $exception->getMessage(),
                'An option that fails the validator callable check should throw an '.InvalidArgumentException::class.'.'
            );
        }
    }

    /**
     * Test that the default option value is returned when no value is set.
     */
    public function test_default_option()
    {
        $command = new Command;
        $command->arguments([
            'foo' => 'bar',
        ]);

        $this->assertSame('bar', $command->option('foo', 'baz'), 'The default value of "baz" for the set option "foo" should not have been returned.');
        $this->assertSame('baz', $command->option('bar', 'baz'), 'The default value of "baz" for the unset option "bar" should have been returned.');
    }

    /**
     * Test that the default option can be a callable.
     */
    public function test_callable_default()
    {
        $command = new Command;
        $command->arguments([
            'foo' => 'bar',
        ]);

        $this->assertSame('bar', $command->option('foo', fn () => 'baz'), 'The default callable for the set option "foo" should not have been called.');
        $this->assertSame('baz', $command->option('bar', fn () => 'baz'), 'The default callable for the unset option "bar" should have been called.');
    }

    /**
     * Test that the default option is returned when the value is an empty string.
     */
    public function test_empty_string_returns_default()
    {
        $command = new Command;
        $command->arguments([
            'foo' => '',
        ]);

        $this->assertSame('baz', $command->option('foo', 'baz'), 'The default value of "baz" for the set option "foo" should have been returned.');
    }
}
