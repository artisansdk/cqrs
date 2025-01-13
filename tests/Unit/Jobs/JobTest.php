<?php

declare(strict_types=1);

namespace ArtisanSdk\CQRS\Tests\Unit\Jobs;

use ArtisanSdk\CQRS\Events\Event;
use ArtisanSdk\CQRS\Jobs\Job;
use ArtisanSdk\CQRS\Tests\Fakes\Commands\{Command, Exceptional, Handler};
use ArtisanSdk\CQRS\Tests\Fakes\Jobs\Job as JobFake;
use ArtisanSdk\CQRS\Tests\Fakes\Log\Logger;
use ArtisanSdk\CQRS\Tests\TestCase;
use RuntimeException;

class JobTest extends TestCase
{
    /**
     * Test that an event can be handled by an array handler definition.
     */
    public function test_object_handler()
    {
        $job = new Job(new Event, new Handler);
        $response = $job->handle();

        $this->assertTrue($response, 'The job should have been run from an object.');
    }

    /**
     * Test that an event can be handled by an array handler definition.
     */
    public function test_array_handler()
    {
        $job = new Job(new Event, [Handler::class, 'handle']);
        $response = $job->handle();

        $this->assertTrue($response, 'The job should have been run from an array handler.');
    }

    /**
     * Test that an event can be handled by a signature handler definition.
     */
    public function test_signature_handler()
    {
        $job = new Job(new Event, Handler::class.'@handle');
        $response = $job->handle();

        $this->assertTrue($response, 'The job should have been run from a signature handler.');
    }

    /**
     * Test that an event can be handled by a runnable.
     */
    public function test_runnable_handler()
    {
        $job = new Job(new Event, [Command::class, 'run']);
        $response = $job->handle();

        $this->assertTrue($response, 'The job should have been run as a command.');
    }

    /**
     * Test that an event can be handled by a runnable.
     */
    public function test_exceptions_are_logged()
    {
        $job = new Job(new Event, [Exceptional::class, 'run']);
        $job->logger(new Logger);
        $response = $job->handle();
        $errors = $job->logger()->logs['error'];

        $this->assertCount(1, $errors, 'The job should have failed resulting in one error message logged.');
        $this->assertSame('ArtisanSdk\CQRS\Tests\Fakes\Commands\Exceptional@run: foo', $errors[0], 'The error should have logged the job signature and the payload.');

        $job = new Job(new Event, Exceptional::class, new Logger);
        $response = $job->handle();
        $errors = $job->logger()->logs['error'];

        $this->assertCount(1, $errors, 'The job should have failed resulting in one error message logged.');
        $this->assertSame('ArtisanSdk\CQRS\Tests\Fakes\Commands\Exceptional: foo', $errors[0], 'The error should have logged the job signature and the payload.');
    }

    /**
     * Test that a RuntimeException deletes the job as non-recoverable.
     */
    public function test_runtime_exception_deletes_job()
    {
        $reference = new JobFake;
        $this->assertFalse($reference->isDeleted(), 'The job should not be deleted by default.');

        $job = new Job(new Event, Command::class);
        $job->setJob($reference);
        $job->failed(new RuntimeException('foo'));

        $this->assertTrue($reference->isDeleted(), 'The job should have been deleted when a RuntimeException is encountered.');
    }
}
