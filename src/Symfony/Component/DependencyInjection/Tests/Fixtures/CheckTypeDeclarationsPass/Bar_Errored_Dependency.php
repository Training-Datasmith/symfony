<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Tests\Fixtures\CheckTypeDeclarationsPass;

class BarErroredDependency
{
    public function __construct(\stdClass $foo)
    {
    }
}
