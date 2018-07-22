<?php

namespace ArtisanSdk\CQRS\Traits;

use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

trait Queues
{
    use Dispatchable,
        InteractsWithQueue,
        Queueable,
        SerializesModels;
}
