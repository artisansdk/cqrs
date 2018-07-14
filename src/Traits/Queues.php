<?php

namespace ArtisanSdk\CQRS\Traits;

use Illuminate\Bus\Queueable;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

trait Queues
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
}
