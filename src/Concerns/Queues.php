<?php

namespace ArtisanSdk\CQRS\Concerns;

use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

trait Queues
{
    use Dispatchable;
    use
        InteractsWithQueue;
    use
        Queueable;
    use
        SerializesModels;
}
