<?php

declare(strict_types=1);

namespace Symfony\Component\HttpKernel\Tests\Fixtures;

class WithPublicObjectProperty
{
    public ?WithPublicObjectProperty $parent = null;
}
