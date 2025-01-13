<?php

declare(strict_types=1);

namespace ArtisanSdk\CQRS\Concerns;

use Illuminate\Queue\{InteractsWithQueue, SerializesModels};

trait Queues
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;
}
