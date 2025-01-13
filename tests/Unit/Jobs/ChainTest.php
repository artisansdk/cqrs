<?php

declare(strict_types=1);

namespace ArtisanSdk\CQRS\Tests\Unit\Jobs;

use ArtisanSdk\CQRS\Events\Event;
use ArtisanSdk\CQRS\Jobs\{Chain, Job, Pending};
use ArtisanSdk\CQRS\Tests\Fakes\Commands\Command;
use ArtisanSdk\CQRS\Tests\TestCase;

class ChainTest extends TestCase
{
    /**
     * Test that a job chain can be constructed.
     */
    public function test_constructor()
    {
        $jobs = [
            new Job(new Event, new Command),
        ];
        $chain = new Chain(Job::class, $jobs);

        $this->assertSame(Job::class, $chain->class, 'The class of the job should be saved on the chain when constructed.');
        $this->assertSame($jobs, $chain->chain, 'The chain of jobs should be saved on the chain when constructed.');
    }

    /**
     * Test that a job chain can be dispatched.
     */
    public function test_dispatch()
    {
        $chain = new Chain(Job::class, [
            new Job(new Event, new Command),
        ]);

        $job = $chain->dispatch(new Event, new Command);

        $this->assertInstanceOf(Pending::class, $job, 'The chain should be dispatched as a pending job chain.');
    }

    /**
     * Test that the next job in the chain can be dispatched.
     */
    public function test_dispatch_next_job_in_chain()
    {
        $chain = (new Job(new Event, new Command))
            ->chain([
                new Job(new Event, new Command),
            ]);
        $chain->dispatchNextJobInChain();

        $this->assertEmpty($chain->chained, 'The job chain should be empty after all jobs have been dispatched.');
    }
}
