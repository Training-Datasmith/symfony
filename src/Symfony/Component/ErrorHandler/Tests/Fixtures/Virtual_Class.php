<?php

declare(strict_types=1);

namespace Symfony\Component\ErrorHandler\Tests\Fixtures;

/**
 * @method string classMethod()
 */
class VirtualClass
{
    use VirtualTrait;
}
