<?php

declare (strict_types=1);
/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Symfony\Component\Dependency_Injection;

/**
 * Parameter represents a parameter reference.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class Parameter implements \Stringable
{
    public function __construct(private readonly string $id)
    {
    }
    public function __toString(): string
    {
        return $this->id;
    }
}