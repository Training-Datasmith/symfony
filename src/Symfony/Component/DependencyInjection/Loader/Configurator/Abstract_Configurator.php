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
namespace Symfony\Component\Dependency_Injection\Loader\Configurator;

use Symfony\Component\Config\Loader\Param_Configurator;
use Symfony\Component\Dependency_Injection\Alias;
use Symfony\Component\Dependency_Injection\Argument\Abstract_Argument;
use Symfony\Component\Dependency_Injection\Argument\Argument_Interface;
use Symfony\Component\Dependency_Injection\Argument\Service_Closure_Argument;
use Symfony\Component\Dependency_Injection\Definition;
use Symfony\Component\Dependency_Injection\Exception\InvalidArgumentException;
use Symfony\Component\Dependency_Injection\Parameter;
use Symfony\Component\Dependency_Injection\Reference;
use Symfony\Component\Expression_Language\Expression;
abstract class Abstract_Configurator
{
    public const FACTORY = 'unknown';
    /**
     * @var \Closure(mixed, bool):mixed|null
     */
    public static ?\Closure $value_pre_processor = null;
    /** @internal */
    protected Definition|Alias|null $definition = null;
    public function __call(string $method, array $args): mixed
    {
        if (method_exists($this, 'set' . $method)) {
            return $this->{'set' . $method}(...$args);
        }
        throw new \BadMethodCallException(\sprintf('Call to undefined method "%s::%s()".', static::class, $method));
    }
    public function __serialize(): array
    {
        throw new \BadMethodCallException('Cannot serialize ' . self::class);
    }
    public function __unserialize(array $data): void
    {
        throw new \BadMethodCallException('Cannot unserialize ' . self::class);
    }
    /**
     * Checks that a value is valid, optionally replacing Definition and Reference configurators by their configure value.
     *
     * @param bool $allowServices whether Definition and Reference are allowed; by default, only scalars, arrays and enum are
     *
     * @return mixed the value, optionally cast to a Definition/Reference
     */
    public static function process_value(mixed $value, bool $allow_services = false): mixed
    {
        if (\is_array($value)) {
            foreach ($value as $k => $v) {
                $value[$k] = static::process_value($v, $allow_services);
            }
            return self::$value_pre_processor ? (self::$value_pre_processor)($value, $allow_services) : $value;
        }
        if (self::$value_pre_processor) {
            $value = (self::$value_pre_processor)($value, $allow_services);
        }
        if ($value instanceof Reference_Configurator) {
            $reference = new Reference($value->id, $value->invalid_behavior);
            return $value instanceof Closure_Reference_Configurator ? new Service_Closure_Argument($reference) : $reference;
        }
        if ($value instanceof Inline_Service_Configurator) {
            $def = $value->definition;
            $value->definition = null;
            return $def;
        }
        if ($value instanceof Param_Configurator) {
            return (string) $value;
        }
        if ($value instanceof self) {
            throw new InvalidArgumentException(\sprintf('"%s()" can be used only at the root of service configuration files.', $value::FACTORY));
        }
        switch (true) {
            case null === $value:
            case \is_scalar($value):
            case $value instanceof \Unit_Enum:
                return $value;
            case $value instanceof \Closure:
                return self::process_closure($value);
            case $value instanceof Argument_Interface:
            case $value instanceof Definition:
            case $value instanceof Expression:
            case $value instanceof Parameter:
            case $value instanceof Abstract_Argument:
            case $value instanceof Reference:
                if ($allow_services) {
                    return $value;
                }
        }
        throw new InvalidArgumentException(\sprintf('Cannot use values of type "%s" in service configuration files.', get_debug_type($value)));
    }
    /**
     * Converts a named closure to dumpable callable.
     *
     * @throws InvalidArgumentException if the closure is anonymous or references a non-static method
     */
    private static function process_closure(\Closure $closure): callable
    {
        $function = new \ReflectionFunction($closure);
        if ($function->is_anonymous()) {
            throw new InvalidArgumentException('Anonymous closure not supported. The closure must be created from a static method or a global function.');
        }
        // Convert global_function(...) closure into 'global_function'
        if (!$class = $function->get_closure_called_class()) {
            return $function->name;
        }
        // Convert Class::method(...) closure into ['Class', 'method']
        if ($function->is_static()) {
            return [$class->name, $function->name];
        }
        throw new InvalidArgumentException(\sprintf('The method "%s::%s(...)" is not static.', $class->name, $function->name));
    }
}