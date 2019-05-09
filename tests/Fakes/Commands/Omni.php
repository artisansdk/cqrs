<?php

namespace ArtisanSdk\CQRS\Tests\Fakes\Commands;

use ArtisanSdk\Contract\Eventable;
use ArtisanSdk\Contract\Taggable;
use ArtisanSdk\Contract\Transactional;
use ArtisanSdk\CQRS\Tests\Fakes\Events\Fizzed;
use ArtisanSdk\CQRS\Tests\Fakes\Events\Fizzing;
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
        return new Model();
    }

    public function afterEvent($entity)
    {
        return new Fizzed($entity);
    }
}
