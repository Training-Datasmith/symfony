<?php

declare(strict_types=1);

namespace Symfony\Component\VarDumper\Tests\Fixtures;

class DateTimeChild extends \DateTimeImmutable
{
    private $addedProperty = 'foo';
}
