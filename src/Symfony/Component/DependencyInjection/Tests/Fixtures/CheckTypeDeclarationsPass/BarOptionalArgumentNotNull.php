<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Tests\Fixtures\CheckTypeDeclarationsPass;

class BarOptionalArgumentNotNull
{
    public $foo;

    public function __construct(int $foo = 1)
    {
        $this->foo = $foo;
    }
}
