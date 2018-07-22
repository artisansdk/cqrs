<?php

namespace ArtisanSdk\CQRS\Tests\Unit\Traits;

use ArtisanSdk\CQRS\Tests\Fakes\Commands\Command;
use ArtisanSdk\CQRS\Tests\TestCase;

class ArgumentsTest extends TestCase
{
    /**
     * Test that the default argument value is returned when no value is set.
     */
    public function testDefaultArgument()
    {
        $command = new Command();
        $command->arguments([
            'foo' => 'bar',
        ]);

        $this->assertSame('bar', $command->argument('foo', 'baz'), 'The default value of "baz" for the set argument "foo" should not have been returned.');
        $this->assertSame('baz', $command->argument('bar', 'baz'), 'The default value of "baz" for the unset argument "bar" should have been returned.');
    }

    /**
     * Test that the default argument can be a callable.
     */
    public function testCallableDefault()
    {
        $command = new Command();
        $command->arguments([
            'foo' => 'bar',
        ]);

        $this->assertSame('bar', $command->argument('foo', function () { return 'baz'; }), 'The default callable for the set argument "foo" should not have been called.');
        $this->assertSame('baz', $command->argument('bar', function () { return 'baz'; }), 'The default callable for the unset argument "bar" should have been called.');
    }

    /**
     * Test that the default argument is returned when the value is an empty string.
     */
    public function testEmptyStringReturnsDefault()
    {
        $command = new Command();
        $command->arguments([
            'foo' => '',
        ]);

        $this->assertSame('baz', $command->argument('foo', 'baz'), 'The default value of "baz" for the set argument "foo" should have been returned.');
    }
}
