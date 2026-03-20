<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Tests\Fixtures;

class ConstructNotExists
{
    public function __construct(NotExist $notExist)
    {
    }
}
