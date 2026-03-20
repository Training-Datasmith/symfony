<?php

declare(strict_types=1);

namespace Symfony\Component\VarDumper\Tests\Fixtures;

interface FooInterface
{
    /**
     * Hello.
     */
    public function foo(?\stdClass $a, ?\stdClass $b = null);
}
