<?php

namespace ArtisanSdk\CQRS\Tests\Unit\Traits;

use ArtisanSdk\CQRS\Tests\Fakes\Commands\Command;
use ArtisanSdk\CQRS\Tests\TestCase;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Fluent;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use stdClass;

class ArgumentsTest extends TestCase
{
    /**
     * Test that the arguments can be set from an arrayable object.
     */
    public function testArraybleArguments()
    {
        $arrayable = new Fluent([
            'foo' => 'bar',
        ]);

        $command = new Command();
        $command->arguments($arrayable);

        $this->assertInstanceOf(Arrayable::class, $arrayable, 'The arguments passed should implement '.Arrayable::class.'.');
        $this->assertSame($arrayable->foo, $command->argument('foo'), 'The arrayable value of "bar" for "foo" argument should have been set.');
    }

    /**
     * Test that an argument is required.
     */
    public function testArgumentIsRequired()
    {
        $command = new Command();
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
    public function testArgumentCannotBeValidatedWithUnsupportedValidator()
    {
        $command = new Command();
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
    public function testArgumentIsValidatedWithCallable()
    {
        $command = new Command();
        $command->arguments([
            'foo' => 'bar',
        ]);

        $value = $command->argument('foo', function ($value, string $name) {
            return true;
        });

        $this->assertSame('bar', $value, 'The callable validator should have returned true and therefore resolved the value as "bar".');

        try {
            $value = $command->argument('foo', function ($value, string $name) {
                return false;
            });
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
    public function testArgumentIsValidatedWithRulesArray()
    {
        $command = new Command();
        $command->arguments([
            'foo' => 'bar',
        ]);

        $value = $command->argument('foo', ['string', 'size:3']);

        $this->assertSame('bar', $value, 'The rules array validator should have validated and therefore resolved the value as "bar".');

        try {
            $value = $command->argument('foo', ['integer']);
        } catch (ValidationException $exception) {
            $this->assertSame(
                'The given data was invalid.',
                $exception->getMessage(),
                'An argument that fails the validator rules passed should throw a '.ValidationException::class.'.'
            );
        }
    }

    /**
     * Test that an argument can be validated using a validator.
     */
    public function testArgumentIsValidatedWithValidator()
    {
        $command = new Command();
        $command->arguments([
            'foo' => 'bar',
        ]);

        $value = $command->argument('foo', $command->makeValidator('foo', 'bar', ['string', 'size:3']));

        $this->assertSame('bar', $value, 'The rules array validator should have validated and therefore resolved the value as "bar".');

        try {
            $value = $command->argument('foo', $command->makeValidator('foo', 'bar', ['integer']));
        } catch (ValidationException $exception) {
            $this->assertSame(
                'The given data was invalid.',
                $exception->getMessage(),
                'An argument that fails the validator rules passed should throw a '.ValidationException::class.'.'
            );
        }
    }

    /**
     * Test that an argument can be validated against a class or interface name.
     */
    public function testArgumentValidatesAgainstClassName()
    {
        $command = new Command();
        $command->arguments([
            'foo' => new stdClass(),
        ]);

        $value = $command->argument('foo', stdClass::class);

        $this->assertInstanceOf(stdClass::class, $value, 'The value for the "foo" argument must be an instance of '.stdClass::class.'.');

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
    public function testArgumentValidatesAgainstTypeCheck()
    {
        $command = new Command();
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
    public function testOptionCanBeValidated()
    {
        $command = new Command();

        $value = $command->option('foo', 'bar', function ($value, string $name) {
            return 'bar' === $value;
        });

        $this->assertSame('bar', $value, 'The callable validator should have returned true and therefore resolved the value as "bar".');

        try {
            $value = $command->option('foo', null, function ($value, string $name) {
                return false;
            });
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
    public function testDefaultOption()
    {
        $command = new Command();
        $command->arguments([
            'foo' => 'bar',
        ]);

        $this->assertSame('bar', $command->option('foo', 'baz'), 'The default value of "baz" for the set option "foo" should not have been returned.');
        $this->assertSame('baz', $command->option('bar', 'baz'), 'The default value of "baz" for the unset option "bar" should have been returned.');
    }

    /**
     * Test that the default option can be a callable.
     */
    public function testCallableDefault()
    {
        $command = new Command();
        $command->arguments([
            'foo' => 'bar',
        ]);

        $this->assertSame('bar', $command->option('foo', function () {
            return 'baz';
        }), 'The default callable for the set option "foo" should not have been called.');
        $this->assertSame('baz', $command->option('bar', function () {
            return 'baz';
        }), 'The default callable for the unset option "bar" should have been called.');
    }

    /**
     * Test that the default option is returned when the value is an empty string.
     */
    public function testEmptyStringReturnsDefault()
    {
        $command = new Command();
        $command->arguments([
            'foo' => '',
        ]);

        $this->assertSame('baz', $command->option('foo', 'baz'), 'The default value of "baz" for the set option "foo" should have been returned.');
    }
}
