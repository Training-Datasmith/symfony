<?php

declare(strict_types=1);

namespace Symfony\Component\ObjectMapper\Tests\Fixtures\ConditionalConstructorArgument;

class ConstructorTarget
{
    public function __construct(public string $name)
    {
    }
}
