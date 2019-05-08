<?php

namespace ArtisanSdk\CQRS\Tests\Jobs;

use ArtisanSdk\CQRS\Jobs\Chain;
use ArtisanSdk\CQRS\Jobs\Job;
use ArtisanSdk\CQRS\Jobs\Pending;
use ArtisanSdk\CQRS\Tests\Fakes\Command;
use ArtisanSdk\CQRS\Tests\TestCase;
use ArtisanSdk\Event\Event;

class ChainTest extends TestCase
{
    /**
     * Test that a job chain can be constructed.
     */
    public function testConstructor()
    {
        $jobs = [
            new Job(new Event(), new Command()),
        ];
        $chain = new Chain(Job::class, $jobs);

        $this->assertSame(Job::class, $chain->class, 'The class of the job should be saved on the chain when constructed.');
        $this->assertSame($jobs, $chain->chain, 'The chain of jobs should be saved on the chain when constructed.');
    }

    /**
     * Test that a job chain can be dispatched.
     */
    public function testDispatch()
    {
        $chain = new Chain(Job::class, [
            new Job(new Event(), new Command()),
        ]);

        $job = $chain->dispatch(new Event(), new Command());

        $this->assertInstanceOf(Pending::class, $job, 'The chain should be dispatched as a pending job chain.');
    }

    /**
     * Test that the next job in the chain can be dispatched.
     */
    public function testDispatchNextJobInChain()
    {
        $chain = (new Job(new Event(), new Command()))
            ->chain([
                new Job(new Event(), new Command()),
            ]);
        $chain->dispatchNextJobInChain();

        $this->assertEmpty($chain->chained, 'The job chain should be empty after all jobs have been dispatched.');
    }
}
