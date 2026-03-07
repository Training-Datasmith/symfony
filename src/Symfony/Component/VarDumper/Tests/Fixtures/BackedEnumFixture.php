<?php

declare(strict_types=1);

namespace Symfony\Component\VarDumper\Tests\Fixtures;

enum BackedEnumFixture: string
{
    case Hearts = 'H';
    case Diamonds = 'D';
    case Clubs = 'C';
    case Spades = 'S';
}
