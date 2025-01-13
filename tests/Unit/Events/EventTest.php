<?php

declare(strict_types=1);

namespace ArtisanSdk\CQRS\Tests\Unit\Events;

use ArtisanSdk\CQRS\Events\Event;
use ArtisanSdk\CQRS\Tests\TestCase;

class EventTest extends TestCase
{
    /**
     * Test that the event can have the event name set.
     */
    public function test_event()
    {
        $event = new Event;
        $returned = $event->event('foo');
        $this->assertSame($returned, $event, 'The event should have been returned by the event() setter.');
        $this->assertSame('foo', $returned->event(), 'The entity should be "foo" because that value was set by the event() setter.');
    }

    /**
     * Test that the event can have an entity set.
     */
    public function test_entity()
    {
        $event = new Event;
        $returned = $event->entity('foo');
        $this->assertSame($returned, $event, 'The event should have been returned by the entity() setter.');
        $this->assertSame('foo', $returned->entity(), 'The entity should be "foo" because that value was set by the entity() setter.');
    }

    /**
     * Test that the public and protected properties of the event can be gotten.
     */
    public function test_properties()
    {
        $event = new Event(['foo' => 'bar']);
        $this->assertSame(
            ['event' => Event::class, 'foo' => 'bar'],
            $event->properties(),
            'The properties() method should return all public and protected properties of the event.'
        );
    }

    /**
     * Test that the event can be converted to JSON.
     */
    public function test_to_json()
    {
        $event = new Event(['foo' => 'bar']);
        $event->entity('foo');
        $this->assertJsonStringEqualsJsonString(
            '{"event":"ArtisanSdk\\\\CQRS\\\\Events\\\\Event","entity":"foo","foo":"bar"}',
            $event->toJson(),
            'The event and payload arguments should be part of the JSON serialized format.'
        );
    }
}
