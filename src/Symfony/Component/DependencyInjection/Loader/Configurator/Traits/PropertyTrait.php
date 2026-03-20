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

trait Property_Trait
{
    /**
     * Sets a specific property.
     *
     * @return $this
     */
    final public function property(string $name, mixed $value): static
    {
        $this->definition->set_property($name, static::process_value($value, true));
        return $this;
    }
}