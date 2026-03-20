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
namespace Symfony\Component\Dependency_Injection\Config;

use Symfony\Component\Config\Resource\Resource_Interface;
/**
 * Tracks container parameters.
 *
 * @author Maxime Steinhausser <maxime.steinhausser@gmail.com>
 *
 * @final
 */
class Container_Parameters_Resource implements Resource_Interface
{
    /**
     * @param array $parameters The container parameters to track
     */
    public function __construct(private readonly array $parameters)
    {
    }
    public function __toString(): string
    {
        return 'container_parameters_' . hash('xxh128', serialize($this->parameters));
    }
    public function get_parameters(): array
    {
        return $this->parameters;
    }
}