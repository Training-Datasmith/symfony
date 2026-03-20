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
namespace Symfony\Component\Dependency_Injection\Loader\Configurator\Traits;

trait Argument_Trait
{
    /**
     * Sets the arguments to pass to the service constructor/factory method.
     *
     * @return $this
     */
    final public function args(array $arguments): static
    {
        $this->definition->set_arguments(static::process_value($arguments, true));
        return $this;
    }
    /**
     * Sets one argument to pass to the service constructor/factory method.
     *
     * @return $this
     */
    final public function arg(string|int $key, mixed $value): static
    {
        $this->definition->set_argument($key, static::process_value($value, true));
        return $this;
    }
}