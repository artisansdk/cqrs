<?php

declare(strict_types=1);

namespace ArtisanSdk\CQRS\Tests\Fakes\Commands;

use ArtisanSdk\Contract\{Eventable, Taggable, Transactional};
use ArtisanSdk\CQRS\Tests\Fakes\Events\{Fizzed, Fizzing};
use ArtisanSdk\CQRS\Tests\Fakes\Models\Model;

/**
 * @tags foo, bar
 */
class Omni extends Command implements Eventable, Taggable, Transactional
{
    public function beforeEvent(array $arguments = [])
    {
        return new Fizzing($arguments);
    }

    public function run()
    {
        return new Model;
    }

    public function afterEvent($entity)
    {
        return new Fizzed($entity);
    }
}
