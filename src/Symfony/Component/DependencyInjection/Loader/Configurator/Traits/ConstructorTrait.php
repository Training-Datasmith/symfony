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

trait Constructor_Trait
{
    /**
     * Sets a static constructor.
     *
     * @return $this
     */
    final public function constructor(string $constructor): static
    {
        $this->definition->set_factory([null, $constructor]);
        return $this;
    }
}