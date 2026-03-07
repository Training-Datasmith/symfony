<?php

declare(strict_types=1);

namespace Symfony\Component\ObjectMapper\Tests\Fixtures\NestedMapping;

class NestedSourceB
{
    public function __construct(
        public string $foo,
    ) {
    }
}
