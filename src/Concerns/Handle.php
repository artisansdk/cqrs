<?php

declare(strict_types=1);

namespace ArtisanSdk\CQRS\Concerns;

use ArtisanSdk\Contract\{Event, Queueable};
use ArtisanSdk\CQRS\Jobs\Job;

trait Handle
{
    /**
     * Handle the event by running this an event handler.
     *
     * @param  Event  $event
     * @return mixed
     */
    public function handle(Event $event)
    {
        if ($this instanceof Queueable) {
            return $this->queue($event);
        }

        return $this->command($this)
            ->arguments($event->properties())
            ->run();
    }

    /**
     * Handle the event by queuing this as a job.
     *
     * @param  Event  $event
     * @return \Illuminate\Foundation\Bus\PendingDispatch
     */
    public function queue(Event $event)
    {
        return Job::dispatch($event, $this);
    }
}
