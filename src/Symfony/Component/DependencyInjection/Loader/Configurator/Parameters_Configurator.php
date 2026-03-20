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
namespace Symfony\Component\Dependency_Injection\Loader\Configurator;

use Symfony\Component\Dependency_Injection\Container_Builder;
use Symfony\Component\Dependency_Injection\Exception\InvalidArgumentException;
use Symfony\Component\Expression_Language\Expression;
/**
 * @author Nicolas Grekas <p@tchwork.com>
 */
class Parameters_Configurator extends Abstract_Configurator
{
    public const FACTORY = 'parameters';
    public function __construct(private readonly Container_Builder $container)
    {
    }
    /**
     * @return $this
     */
    final public function set(string $name, mixed $value): static
    {
        if ($value instanceof Expression) {
            throw new InvalidArgumentException(\sprintf('Using an expression in parameter "%s" is not allowed.', $name));
        }
        $this->container->set_parameter($name, static::process_value($value, true));
        return $this;
    }
    /**
     * @return $this
     */
    final public function __invoke(string $name, mixed $value): static
    {
        return $this->set($name, $value);
    }
}