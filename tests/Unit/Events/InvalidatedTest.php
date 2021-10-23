<?php

namespace ArtisanSdk\CQRS\Tests\Unit\Events;

use ArtisanSdk\CQRS\Events\Invalidated;
use ArtisanSdk\CQRS\Tests\TestCase;

class InvalidatedTest extends TestCase
{
    /**
     * Test that an event can be constructed and the tags getter/setter.
     */
    public function testTags()
    {
        $event = new Invalidated();
        $this->assertEmpty($event->tags(), 'The tags should be empty since Invalidated was constructed without tags.');

        $event = new Invalidated(['foo', 'bar']);
        $this->assertSame(['foo', 'bar'], $event->tags(), 'The tags should contain "foo" and "bar" because those tags were passed when constructed.');

        $returned = $event->tags(['foo']);
        $this->assertSame($returned, $event, 'The event should have been returned by the tags() setter.');
        $this->assertSame(['foo'], $returned->tags(), 'The tags should contain only "foo" because that tag was set by the tags() setter.');
    }
}
