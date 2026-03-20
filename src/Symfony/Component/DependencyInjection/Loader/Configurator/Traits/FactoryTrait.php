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
use Symfony\Component\Dependency_Injection\Loader\Configurator\Reference_Configurator;
use Symfony\Component\Expression_Language\Expression;
trait Factory_Trait
{
    /**
     * Sets a factory.
     *
     * @return $this
     */
    final public function factory(string|array|\Closure|Reference_Configurator|Expression $factory): static
    {
        if (\is_string($factory) && 1 === substr_count($factory, ':')) {
            $factory_parts = explode(':', $factory);
            throw new InvalidArgumentException(\sprintf('Invalid factory "%s": the "service:method" notation is not available when using PHP-based DI configuration. Use "[service(\'%s\'), \'%s\']" instead.', $factory, $factory_parts[0], $factory_parts[1]));
        }
        if ($factory instanceof Expression) {
            $factory = '@=' . $factory;
        }
        $this->definition->set_factory(static::process_value($factory, true));
        return $this;
    }
}