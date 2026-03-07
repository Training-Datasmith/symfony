<?php

declare(strict_types=1);

namespace Symfony\Component\Config\Tests\Fixtures\Resource;

if (!class_exists(MissingClass::class)) {
    class ConditionalClass
    {
    }
}
