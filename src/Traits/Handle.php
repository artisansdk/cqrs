<?php

namespace ArtisanSdk\CQRS\Traits;

use ArtisanSdk\Contract\Event;
use ArtisanSdk\Contract\Queueable;
use ArtisanSdk\CQRS\Jobs\Job;

trait Handle
{
    /**
     * Handle the event by running this an event handler.
     *
     * @param \ArtisanSdk\Contract\Event $event
     *
     * @return mixed
     */
    public function handle(Event $event)
    {
        if ($this instanceof Queueable) {
            return $this->queue($event);
        }

        $properties = $event->properties();

        return $this->command($this)
            ->arguments($properties['payload'])
            ->run();
    }

    /**
     * Handle the event by queuing this as a job.
     *
     * @param \ArtisanSdk\Contract\Event $event
     *
     * @return \Illuminate\Foundation\Bus\PendingDispatch
     */
    public function queue(Event $event)
    {
        return Job::dispatch($event, $this);
    }
}
