<?php

declare(strict_types=1);

namespace Symfony\Component\Routing\Tests\Fixtures\AttributedClasses;

trait FooTrait
{
    public function doBar()
    {
        self::class;
        if (true) {
        }
    }
}
