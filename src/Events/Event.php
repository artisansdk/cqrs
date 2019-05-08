<?php

namespace ArtisanSdk\CQRS\Events;

use ArtisanSdk\Contract\Event as Contract;
use Illuminate\Queue\SerializesModels;
use ReflectionObject;

/**
 * Event Base Class.
 *
 * @example $event = new Event(new User)
 *          $event->toArray() // ['event' => 'Event', 'payload' => $user]
 */
class Event implements Contract
{
    use SerializesModels;

    /**
     * The event class name.
     *
     * @var string
     */
    protected $event;

    /**
     * The entity class name.
     *
     * @var string
     */
    protected $entity;

    /**
     * Inject the payload.
     *
     * @param mixed $payload
     */
    public function __construct()
    {
        $this->event = static::class;

        $payload = func_num_args() ? func_get_arg(0) : [];
        foreach ($payload as $key => $value) {
            $this->$key = $value;
        }
    }

    /**
     * Get the properties of the event as a collection.
     *
     * @return \Illuminate\Support\Collection
     */
    protected function getProperties()
    {
        $properties = collect();

        foreach ((new ReflectionObject($this))->getProperties() as $property) {
            if ( ! $property->isPrivate()) {
                $value = $this->getPropertyValue($property);
                if ( ! is_null($value)) {
                    $properties->put($property->name, $value);
                }
            }
        }

        return $properties;
    }

    /**
     * Get the event's properties in an array.
     *
     * @return array
     */
    public function properties()
    {
        return $this->getProperties()->all();
    }

    /**
     * Get or set the event class name dynamically.
     *
     * @param string $event
     *
     * @return string|self
     */
    public function event($event = null)
    {
        if (is_null($event)) {
            return $this->event;
        }

        $this->event = $event;

        return $this;
    }

    /**
     * Get or set the entity class name dynamically.
     *
     * @param string $entity
     *
     * @return string|self
     */
    public function entity($entity = null)
    {
        if (is_null($entity)) {
            return $this->entity;
        }

        $this->entity = $entity;

        return $this;
    }

    /**
     * Get the event's properties as an array structure.
     *
     * @return array
     */
    public function toArray()
    {
        return $this->getProperties()->toArray();
    }

    /**
     * Convert the object into something JSON serializable.
     *
     * @return array
     */
    public function jsonSerialize()
    {
        return $this->toArray();
    }

    /**
     * Convert the event instance to JSON.
     *
     * @param int $options
     *
     * @return string
     */
    public function toJson($options = 0)
    {
        return json_encode($this->jsonSerialize(), $options);
    }
}
