<?php

declare(strict_types=1);

namespace Symfony\Component\Config\Tests\Fixtures;

enum IntegerBackedTestEnum: int
{
    case One = 1;
    case Two = 2;
}
