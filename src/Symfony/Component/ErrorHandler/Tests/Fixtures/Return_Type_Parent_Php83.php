<?php

declare(strict_types=1);

namespace Symfony\Component\ErrorHandler\Tests\Fixtures;

abstract class ReturnTypeParentPhp83
{
    public const string FOO = 'foo';
    public const string|int BAR = 'bar';

    /**
     * @return self::FOO
     */
    public function classConstantWithType()
    {
    }

    /**
     * @return self::BAR
     */
    public function classConstantWithUnionType()
    {
    }
}
