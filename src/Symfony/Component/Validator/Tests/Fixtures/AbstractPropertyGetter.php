<?php

declare(strict_types=1);

namespace Symfony\Component\Validator\Tests\Fixtures;

abstract class AbstractPropertyGetter implements PropertyGetterInterface
{
    private $property;

    public function getProperty()
    {
        return $this->property;
    }
}
