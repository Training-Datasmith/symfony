<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Tests\Fixtures\CheckTypeDeclarationsPass;

class FooObject
{
    public function __construct(object $foo)
    {
    }
}
