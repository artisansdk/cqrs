<?php

declare(strict_types=1);

namespace ArtisanSdk\CQRS\Tests\Fakes\Commands;

use Exception;

class Exceptional extends Transactional
{
    public function run()
    {
        throw new Exception('foo');
    }
}
