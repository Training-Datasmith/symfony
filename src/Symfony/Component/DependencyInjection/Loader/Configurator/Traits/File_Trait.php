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

trait File_Trait
{
    /**
     * Sets a file to require before creating the service.
     *
     * @return $this
     */
    final public function file(string $file): static
    {
        $this->definition->set_file($file);
        return $this;
    }
}