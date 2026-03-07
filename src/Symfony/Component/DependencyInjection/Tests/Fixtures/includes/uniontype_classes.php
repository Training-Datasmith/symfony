<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Tests\Compiler;

class UnionScalars
{
    public function __construct(int|float $timeout)
    {
    }
}

class UnionClasses
{
    public function __construct(CollisionA|CollisionB $collision)
    {
    }
}

class UnionNull
{
    public function __construct(CollisionInterface|null $c)
    {
    }
}
