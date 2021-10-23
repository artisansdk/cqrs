<?php

namespace ArtisanSdk\CQRS\Events;

use ArtisanSdk\Contract\Event as Contract;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Queue\SerializesModels;
use ReflectionObject;

/**
 * Event Base.
 *
 * @example $event = new Event(['foo' => 'bar'])
 *          $event->toArray() // ['event' => 'Event', 'foo' => 'bar']
 *          $event->event('Foo\Bar\Event')
 *          $event->entity('Foo\Bar\Entity')
 *
 * @method string|self event(string $name)
 * @method string|self entity(string $entity)
 * @method array       properties()
 * @method array       toArray()
 * @method string      toJson(int $options)
 * @method array       jsonSerialize()
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
        if ($payload instanceof Arrayable) {
            $payload = $payload->toArray();
        }
        if (is_array($payload)) {
            foreach ($payload as $key => $value) {
                $this->$key = $value;
            }
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
