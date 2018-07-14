<?php

namespace ArtisanSdk\CQRS\Traits;

use Illuminate\Bus\Queueable;
// use Illuminate\Foundation\Bus\Dispatchable; // @todo bring this back once Bus\Dispatchable is available
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

trait Queues
{
    use /*@todo Dispatchable,*/ InteractsWithQueue, Queueable, SerializesModels;
}
