<?php

declare(strict_types=1);

namespace Symfony\Component\VarDumper\Tests\Fixtures;

class ReflectionUnionTypeWithIntersectionFixture
{
    public (\Traversable&\Countable)|null $a;
}
