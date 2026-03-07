<?php

declare(strict_types=1);

namespace Symfony\Component\Config\Tests\Fixtures;

enum StringBackedTestEnum2: string
{
    case Foo = 'foo';
    case Bar = 'bar';
}
