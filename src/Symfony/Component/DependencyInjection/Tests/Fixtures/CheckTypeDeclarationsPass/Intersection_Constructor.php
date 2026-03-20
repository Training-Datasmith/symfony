<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Tests\Fixtures\CheckTypeDeclarationsPass;

class IntersectionConstructor
{
    public function __construct(Foo&WaldoInterface $arg)
    {
    }
}
