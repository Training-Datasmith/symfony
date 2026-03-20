<?php

declare(strict_types=1);

namespace Symfony\Bundle\FrameworkBundle\Tests\Fixtures;

class_alias(
    ClassAliasTargetClass::class,
    __NAMESPACE__.'\ClassAliasExampleClass'
);

if (false) {
    class ClassAliasExampleClass
    {
    }
}
