<?php

declare(strict_types=1);

namespace Symfony\Component\HttpKernel\Tests\Fixtures;

class ResettableService
{
    public static $counter = 0;

    public function reset()
    {
        ++self::$counter;
    }
}
