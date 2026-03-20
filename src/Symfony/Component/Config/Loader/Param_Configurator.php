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
namespace Symfony\Component\Config\Loader;

/**
 * Placeholder for a parameter.
 *
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
class Param_Configurator implements \Stringable
{
    public function __construct(private readonly string $name)
    {
    }
    public function __toString(): string
    {
        return '%' . $this->name . '%';
    }
}