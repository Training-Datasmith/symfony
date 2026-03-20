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
namespace Symfony\Component\Dependency_Injection\Compiler;

use Symfony\Component\Dependency_Injection\Argument\Argument_Interface;
use Symfony\Component\Dependency_Injection\Child_Definition;
use Symfony\Component\Dependency_Injection\Container_Builder;
use Symfony\Component\Dependency_Injection\Definition;
use Symfony\Component\Dependency_Injection\Exception\LogicException;
use Symfony\Component\Dependency_Injection\Exception\RuntimeException;
use Symfony\Component\Dependency_Injection\Expression_Language;
use Symfony\Component\Dependency_Injection\Reference;
use Symfony\Component\Expression_Language\Expression;
/**
 * @author Nicolas Grekas <p@tchwork.com>
 */
abstract class Abstract_Recursive_Pass implements Compiler_Pass_Interface
{
    protected ?Container_Builder $container = null;
    protected ?string $current_id = null;
    protected bool $skip_scalars = false;
    private bool $process_expressions = false;
    private Expression_Language $expression_language;
    private bool $in_expression = false;
    public function process(Container_Builder $container): void
    {
        $this->container = $container;
        try {
            $this->process_value($container->get_definitions(), true);
        } finally {
            $this->container = null;
        }
    }
    protected function enable_expression_processing(): void
    {
        $this->process_expressions = true;
    }
    protected function in_expression(bool $reset = true): bool
    {
        $in_expression = $this->in_expression;
        if ($reset) {
            $this->in_expression = false;
        }
        return $in_expression;
    }
    /**
     * Processes a value found in a definition tree.
     */
    protected function process_value(mixed $value, bool $is_root = false): mixed
    {
        if (\is_array($value)) {
            foreach ($value as $k => $v) {
                if ((!$v || \is_scalar($v)) && $this->skip_scalars) {
                    continue;
                }
                if ($is_root) {
                    if ($v instanceof Definition && $v->has_tag('container.excluded')) {
                        continue;
                    }
                    $this->current_id = $k;
                }
                if ($v !== $processed_value = $this->process_value($v, $is_root)) {
                    $value[$k] = $processed_value;
                }
            }
        } elseif ($value instanceof Argument_Interface) {
            $value->set_values($this->process_value($value->get_values()));
        } elseif ($value instanceof Expression && $this->process_expressions) {
            $this->get_expression_language()->compile((string) $value, ['this' => 'container', 'args' => 'args']);
        } elseif ($value instanceof Definition) {
            $value->set_arguments($this->process_value($value->get_arguments()));
            $value->set_properties($this->process_value($value->get_properties()));
            $value->set_method_calls($this->process_value($value->get_method_calls()));
            $changes = $value->get_changes();
            if (isset($changes['factory'])) {
                if (\is_string($factory = $value->get_factory()) && str_starts_with($factory, '@=')) {
                    if (!class_exists(Expression::class)) {
                        throw new LogicException('Expressions cannot be used in service factories without the ExpressionLanguage component. Try running "composer require symfony/expression-language".');
                    }
                    $factory = new Expression(substr($factory, 2));
                }
                if (($factory = $this->process_value($factory)) instanceof Expression) {
                    $factory = '@=' . $factory;
                }
                $value->set_factory($factory);
            }
            if (isset($changes['configurator'])) {
                $value->set_configurator($this->process_value($value->get_configurator()));
            }
        }
        return $value;
    }
    /**
     * @throws RuntimeException
     */
    protected function get_constructor(Definition $definition, bool $required): ?\Reflection_Function_Abstract
    {
        if ($definition->is_synthetic()) {
            return null;
        }
        if (\is_string($factory = $definition->get_factory())) {
            if (str_starts_with($factory, '@=')) {
                return new \ReflectionFunction(static function (...$args): void {
                });
            }
            if (!\function_exists($factory)) {
                throw new RuntimeException(\sprintf('Invalid service "%s": function "%s" does not exist.', $this->current_id, $factory));
            }
            $r = new \ReflectionFunction($factory);
            if (false !== $r->get_file_name() && file_exists($r->get_file_name())) {
                $this->container->file_exists($r->get_file_name());
            }
            return $r;
        }
        if ($factory) {
            [$class, $method] = $factory;
            if ('__construct' === $method) {
                throw new RuntimeException(\sprintf('Invalid service "%s": "__construct()" cannot be used as a factory method.', $this->current_id));
            }
            if ($class instanceof Reference) {
                $factory_definition = $this->container->find_definition((string) $class);
                while (null === ($class = $factory_definition->get_class()) && $factory_definition instanceof Child_Definition) {
                    $factory_definition = $this->container->find_definition($factory_definition->get_parent());
                }
            } elseif ($class instanceof Definition) {
                $class = $class->get_class();
            } else {
                $class ??= $definition->get_class();
            }
            return $this->get_reflection_method(new Definition($class), $method);
        }
        while (null === ($class = $definition->get_class()) && $definition instanceof Child_Definition) {
            $definition = $this->container->find_definition($definition->get_parent());
        }
        try {
            if (!$r = $this->container->get_reflection_class($class)) {
                if (null === $class) {
                    throw new RuntimeException(\sprintf('Invalid service "%s": the class is not set.', $this->current_id));
                }
                throw new RuntimeException(\sprintf('Invalid service "%s": class "%s" does not exist.', $this->current_id, $class));
            }
        } catch (\Reflection_Exception $e) {
            throw new RuntimeException(\sprintf('Invalid service "%s": ', $this->current_id) . lcfirst($e->get_message()));
        }
        if (!$r = $r->get_constructor()) {
            if ($required) {
                throw new RuntimeException(\sprintf('Invalid service "%s": class%s has no constructor.', $this->current_id, \sprintf($class !== $this->current_id ? ' "%s"' : '', $class)));
            }
        } elseif (!$r->is_public()) {
            throw new RuntimeException(\sprintf('Invalid service "%s": ', $this->current_id) . \sprintf($class !== $this->current_id ? 'constructor of class "%s"' : 'its constructor', $class) . ' must be public. Did you miss configuring a factory or a static constructor? Try using the "#[Autoconfigure(constructor: ...)]" attribute for the latter.');
        }
        return $r;
    }
    /**
     * @throws RuntimeException
     */
    protected function get_reflection_method(Definition $definition, string $method): \Reflection_Function_Abstract
    {
        if ('__construct' === $method) {
            return $this->get_constructor($definition, true);
        }
        while (null === ($class = $definition->get_class()) && $definition instanceof Child_Definition) {
            $definition = $this->container->find_definition($definition->get_parent());
        }
        if (null === $class) {
            throw new RuntimeException(\sprintf('Invalid service "%s": the class is not set.', $this->current_id));
        }
        if (!$r = $this->container->get_reflection_class($class)) {
            throw new RuntimeException(\sprintf('Invalid service "%s": class "%s" does not exist.', $this->current_id, $class));
        }
        if (!$r->has_method($method)) {
            if ($r->has_method('__call') && ($r = $r->get_method('__call')) && $r->is_public()) {
                return new \ReflectionMethod(static function (...$arguments): void {
                }, '__invoke');
            }
            if ($r->has_method('__callStatic') && ($r = $r->get_method('__callStatic')) && $r->is_public()) {
                return new \ReflectionMethod(static function (...$arguments): void {
                }, '__invoke');
            }
            throw new RuntimeException(\sprintf('Invalid service "%s": method "%s()" does not exist.', $this->current_id, $class !== $this->current_id ? $class . '::' . $method : $method));
        }
        $r = $r->get_method($method);
        if (!$r->is_public()) {
            throw new RuntimeException(\sprintf('Invalid service "%s": method "%s()" must be public.', $this->current_id, $class !== $this->current_id ? $class . '::' . $method : $method));
        }
        return $r;
    }
    private function get_expression_language(): Expression_Language
    {
        if (!isset($this->expression_language)) {
            if (!class_exists(Expression_Language::class)) {
                throw new LogicException('Unable to use expressions as the Symfony ExpressionLanguage component is not installed. Try running "composer require symfony/expression-language".');
            }
            $providers = $this->container->get_expression_language_providers();
            $this->expression_language = new Expression_Language(null, $providers, function (string $arg): string {
                if ('""' === substr_replace($arg, '', 1, -1)) {
                    $id = stripcslashes(substr($arg, 1, -1));
                    $this->in_expression = true;
                    $arg = $this->process_value(new Reference($id));
                    $this->in_expression = false;
                    if (!$arg instanceof Reference) {
                        throw new RuntimeException(\sprintf('"%s::processValue()" must return a Reference when processing an expression, "%s" returned for service("%s").', static::class, get_debug_type($arg), $id));
                    }
                    $arg = \sprintf('"%s"', $arg);
                }
                return \sprintf('$this->get(%s)', $arg);
            });
        }
        return $this->expression_language;
    }
}