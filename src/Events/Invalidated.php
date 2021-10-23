<?php

namespace ArtisanSdk\CQRS\Events;

/**
 * Invalidated Event.
 *
 * {@inheritdoc}
 *
 * @method string[]|self tags(array $tags)
 */
class Invalidated extends Event
{
    /**
     * The tags to be invalidated.
     *
     * @var string[]
     */
    protected $tags = [];

    /**
     * Prepare the event payload.
     *
     * @param array $tags
     */
    public function __construct(array $tags = [])
    {
        $this->tags = $tags;
    }

    /**
     * Get or set the event tags dynamically.
     *
     * @param string[]|null $tags
     *
     * @return string[]|self
     */
    public function tags(array $tags = null)
    {
        if (is_null($tags)) {
            return $this->tags;
        }

        $this->tags = $tags;

        return $this;
    }
}
