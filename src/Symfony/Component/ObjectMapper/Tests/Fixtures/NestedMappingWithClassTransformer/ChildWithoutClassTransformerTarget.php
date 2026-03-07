<?php

declare(strict_types=1);

namespace Symfony\Component\ObjectMapper\Tests\Fixtures\NestedMappingWithClassTransformer;

class ChildWithoutClassTransformerTarget
{
    public string $name;
    public bool $propertyTransformed = false;
}
