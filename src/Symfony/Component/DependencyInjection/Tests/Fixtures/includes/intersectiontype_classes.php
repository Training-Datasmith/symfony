<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Tests\Compiler;

interface AnotherInterface
{
}

class IntersectionClasses
{
    public function __construct(CollisionInterface&AnotherInterface $collision)
    {
    }
}
