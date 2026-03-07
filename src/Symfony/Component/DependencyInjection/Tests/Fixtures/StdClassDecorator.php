<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Tests\Fixtures;

final class StdClassDecorator
{
    public $foo;

    public function __construct(\stdClass $foo)
    {
        $this->foo = $foo;
    }
}
