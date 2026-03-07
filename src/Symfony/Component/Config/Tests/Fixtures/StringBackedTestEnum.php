<?php

declare(strict_types=1);

namespace Symfony\Component\Config\Tests\Fixtures;

enum StringBackedTestEnum: string
{
    case Foo = 'foo';
    case BarBaz = 'bar baz';
}
