<?php

namespace ArtisanSdk\CQRS\Traits;

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
