<?php

namespace ArtisanSdk\CQRS\Tests\Events;

use ArtisanSdk\CQRS\Tests\TestCase;
use ArtisanSdk\Event\Event;

class EventTest extends TestCase
{
    /**
     * Test that the event can be converted to JSON.
     */
    public function testToJson()
    {
        $event = new Event(['foo' => 'bar']);
        $this->assertJsonStringEqualsJsonString(
            '{"event":"ArtisanSdk\\\CQRS\\\Events\\\Event","payload":{"foo":"bar"}}',
            $event->toJson(),
            'The event and payload should be part of the JSON serialized format.'
        );
    }
}
