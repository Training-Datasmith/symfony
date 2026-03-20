<?php

declare(strict_types=1);

namespace Symfony\Component\Serializer\Tests\Fixtures\Attributes;

use Symfony\Component\Serializer\Attribute\SerializedName;

class SerializedNameAttributeDummy
{
    #[SerializedName('@foo')]
    public string $foo;
}
