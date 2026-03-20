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

trait Public_Trait
{
    /**
     * @return $this
     */
    final public function public(): static
    {
        $this->definition->set_public(true);
        return $this;
    }
    /**
     * @return $this
     */
    final public function private(): static
    {
        $this->definition->set_public(false);
        return $this;
    }
}