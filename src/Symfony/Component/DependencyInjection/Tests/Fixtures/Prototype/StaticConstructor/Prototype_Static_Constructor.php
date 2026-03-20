<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Tests\Fixtures\Prototype\StaticConstructor;

class PrototypeStaticConstructor implements PrototypeStaticConstructorInterface
{
    private function __construct()
    {
    }

    public static function create(): static
    {
        return new self();
    }
}
