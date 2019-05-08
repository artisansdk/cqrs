<?php

namespace ArtisanSdk\CQRS\Events;

class Invalidated extends Event
{
    /**
     * Prepare the event payload.
     *
     * @param array $tags
     */
    public function __construct(array $tags = [])
    {
        $this->tags = $tags;
    }
}
