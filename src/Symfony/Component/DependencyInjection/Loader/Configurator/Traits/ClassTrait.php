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

trait Class_Trait
{
    /**
     * Sets the service class.
     *
     * @return $this
     */
    final public function class(?string $class): static
    {
        $this->definition->set_class($class);
        return $this;
    }
}