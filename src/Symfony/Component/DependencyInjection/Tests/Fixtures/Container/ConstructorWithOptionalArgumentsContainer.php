<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Tests\Fixtures\Container;

class ConstructorWithOptionalArgumentsContainer
{
    public function __construct($optionalArgument = 'foo')
    {
    }
}
