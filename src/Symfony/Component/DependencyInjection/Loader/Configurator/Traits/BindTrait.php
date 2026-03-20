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

use Symfony\Component\Dependency_Injection\Argument\Bound_Argument;
use Symfony\Component\Dependency_Injection\Loader\Configurator\Defaults_Configurator;
use Symfony\Component\Dependency_Injection\Loader\Configurator\Instanceof_Configurator;
trait Bind_Trait
{
    /**
     * Sets bindings.
     *
     * Bindings map $named or FQCN arguments to values that should be
     * injected in the matching parameters (of the constructor, of methods
     * called and of controller actions).
     *
     * @param string $nameOrFqcn A parameter name with its "$" prefix, or a FQCN
     * @param mixed  $valueOrRef The value or reference to bind
     *
     * @return $this
     */
    final public function bind(string $name_or_fqcn, mixed $value_or_ref): static
    {
        $value_or_ref = static::process_value($value_or_ref, true);
        $bindings = $this->definition->get_bindings();
        $type = $this instanceof Defaults_Configurator ? Bound_Argument::DEFAULTS_BINDING : ($this instanceof Instanceof_Configurator ? Bound_Argument::INSTANCEOF_BINDING : Bound_Argument::SERVICE_BINDING);
        $bindings[$name_or_fqcn] = new Bound_Argument($value_or_ref, true, $type, $this->path ?? null);
        $this->definition->set_bindings($bindings);
        return $this;
    }
}