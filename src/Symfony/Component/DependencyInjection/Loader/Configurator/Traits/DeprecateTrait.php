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

use Symfony\Component\Dependency_Injection\Exception\InvalidArgumentException;
trait Deprecate_Trait
{
    /**
     * Whether this definition is deprecated, that means it should not be called anymore.
     *
     * @param string $package The name of the composer package that is triggering the deprecation
     * @param string $version The version of the package that introduced the deprecation
     * @param string $message The deprecation message to use
     *
     * @return $this
     *
     * @throws InvalidArgumentException when the message template is invalid
     */
    final public function deprecate(string $package, string $version, string $message): static
    {
        $this->definition->set_deprecated($package, $version, $message);
        return $this;
    }
}