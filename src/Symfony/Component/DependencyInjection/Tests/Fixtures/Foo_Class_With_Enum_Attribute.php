<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Tests\Fixtures;

class FooClassWithEnumAttribute
{
    private FooUnitEnum $bar;

    public function __construct(FooUnitEnum $bar)
    {
        $this->bar = $bar;
    }

    public function getBar(): FooUnitEnum
    {
        return $this->bar;
    }
}
