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

use Symfony\Component\Dependency_Injection\Child_Definition;
use Symfony\Component\Dependency_Injection\Exception\InvalidArgumentException;
use Symfony\Component\Dependency_Injection\Loader\Configurator\From_Callable_Configurator;
use Symfony\Component\Dependency_Injection\Loader\Configurator\Reference_Configurator;
use Symfony\Component\Expression_Language\Expression;
trait From_Callable_Trait
{
    final public function from_callable(string|array|\Closure|Reference_Configurator|Expression $callable): From_Callable_Configurator
    {
        if ($this->definition instanceof Child_Definition) {
            throw new InvalidArgumentException('The configuration key "parent" is unsupported when using "fromCallable()".');
        }
        foreach (['synthetic' => 'isSynthetic', 'factory' => 'getFactory', 'file' => 'getFile', 'arguments' => 'getArguments', 'properties' => 'getProperties', 'configurator' => 'getConfigurator', 'calls' => 'getMethodCalls'] as $key => $method) {
            if ($this->definition->{$method}()) {
                throw new InvalidArgumentException(\sprintf('The configuration key "%s" is unsupported when using "fromCallable()".', $key));
            }
        }
        $this->definition->set_factory(['Closure', 'fromCallable']);
        if (\is_string($callable) && 1 === substr_count($callable, ':')) {
            $parts = explode(':', $callable);
            throw new InvalidArgumentException(\sprintf('Invalid callable "%s": the "service:method" notation is not available when using PHP-based DI configuration. Use "[service(\'%s\'), \'%s\']" instead.', $callable, $parts[0], $parts[1]));
        }
        if ($callable instanceof Expression) {
            $callable = '@=' . $callable;
        }
        $this->definition->set_arguments([static::process_value($callable, true)]);
        if ('Closure' !== ($this->definition->get_class() ?? 'Closure')) {
            $this->definition->set_lazy(true);
        } else {
            $this->definition->set_class('Closure');
        }
        return new From_Callable_Configurator($this, $this->definition);
    }
}