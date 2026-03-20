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

use Symfony\Component\Dependency_Injection\Loader\Configurator\Reference_Configurator;
trait Configurator_Trait
{
    /**
     * Sets a configurator to call after the service is fully initialized.
     *
     * @return $this
     */
    final public function configurator(string|array|\Closure|Reference_Configurator $configurator): static
    {
        $this->definition->set_configurator(static::process_value($configurator, true));
        return $this;
    }
}