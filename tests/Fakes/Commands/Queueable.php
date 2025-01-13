<?php

declare(strict_types=1);

namespace ArtisanSdk\CQRS\Tests\Fakes\Commands;

use ArtisanSdk\Contract\{Event, Queueable as Contract};
use ArtisanSdk\CQRS\Concerns\Queues;

class Queueable extends Command implements Contract
{
    use Queues;

    public Event $event;

    public function __construct()
    {
        $this->queue = 'default';
        $this->connection = 'default';
        $this->delay = 10;
    }

    public function queue(Event $event)
    {
        $this->event = $event;

        return parent::queue($event);
    }
}
