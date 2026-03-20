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

trait Share_Trait
{
    /**
     * Sets if the service must be shared or not.
     *
     * @return $this
     */
    final public function share(bool $shared = true): static
    {
        $this->definition->set_shared($shared);
        return $this;
    }
}