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
namespace Symfony\Component\Dependency_Injection\Dumper;

use Symfony\Component\Dependency_Injection\Alias;
use Symfony\Component\Dependency_Injection\Argument\Abstract_Argument;
use Symfony\Component\Dependency_Injection\Argument\Argument_Interface;
use Symfony\Component\Dependency_Injection\Argument\Iterator_Argument;
use Symfony\Component\Dependency_Injection\Argument\Service_Closure_Argument;
use Symfony\Component\Dependency_Injection\Argument\Service_Locator_Argument;
use Symfony\Component\Dependency_Injection\Argument\Tagged_Iterator_Argument;
use Symfony\Component\Dependency_Injection\Container_Interface;
use Symfony\Component\Dependency_Injection\Definition;
use Symfony\Component\Dependency_Injection\Exception\LogicException;
use Symfony\Component\Dependency_Injection\Exception\RuntimeException;
use Symfony\Component\Dependency_Injection\Parameter;
use Symfony\Component\Dependency_Injection\Reference;
use Symfony\Component\Expression_Language\Expression;
use Symfony\Component\Yaml\Dumper as YmlDumper;
use Symfony\Component\Yaml\Parser;
use Symfony\Component\Yaml\Tag\Tagged_Value;
use Symfony\Component\Yaml\Yaml;
/**
 * YamlDumper dumps a service container as a YAML string.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class Yaml_Dumper extends Dumper
{
    private Yml_Dumper $dumper;
    /**
     * Dumps the service container as an YAML string.
     */
    public function dump(array $options = []): string
    {
        if (!class_exists(Yml_Dumper::class)) {
            throw new LogicException('Unable to dump the container as the Symfony Yaml Component is not installed. Try running "composer require symfony/yaml".');
        }
        $this->dumper ??= new Yml_Dumper();
        return $this->add_parameters() . "\n" . $this->add_services();
    }
    private function add_service(string $id, Definition $definition): string
    {
        $code = "    {$this->dumper->dump($id)}:\n";
        if ($class = $definition->get_class()) {
            if (str_starts_with($class, '\\')) {
                $class = substr($class, 1);
            }
            $code .= \sprintf("        class: %s\n", $this->dumper->dump($this->container->resolve_env_placeholders($class)));
        }
        if (!$definition->is_private()) {
            $code .= \sprintf("        public: %s\n", $definition->is_public() ? 'true' : 'false');
        }
        $tags_code = '';
        $tags = $definition->get_tags();
        $tags['container.error'] = array_map(static fn($e): array => ['message' => $e], $definition->get_errors());
        foreach ($tags as $name => $tags) {
            foreach ($tags as $attributes) {
                $att = [];
                foreach ($attributes as $key => $value) {
                    $att[] = \sprintf('%s: %s', $this->dumper->dump($key), $this->dumper->dump($value));
                }
                $att = $att ? ': { ' . implode(', ', $att) . ' }' : '';
                $tags_code .= \sprintf("            - %s%s\n", $this->dumper->dump($name), $att);
            }
        }
        if ($tags_code) {
            $code .= "        tags:\n" . $tags_code;
        }
        if ($definition->get_file()) {
            $code .= \sprintf("        file: %s\n", $this->dumper->dump($this->container->resolve_env_placeholders($definition->get_file())));
        }
        if ($definition->is_synthetic()) {
            $code .= "        synthetic: true\n";
        }
        if ($definition->is_deprecated()) {
            $code .= "        deprecated:\n";
            foreach ($definition->get_deprecation('%service_id%') as $key => $value) {
                if ('' !== $value) {
                    $code .= \sprintf("            %s: %s\n", $key, $this->dumper->dump($value));
                }
            }
        }
        if ($definition->is_autowired()) {
            $code .= "        autowire: true\n";
        }
        if ($definition->is_autoconfigured()) {
            $code .= "        autoconfigure: true\n";
        }
        if ($definition->is_abstract()) {
            $code .= "        abstract: true\n";
        }
        if ($definition->is_lazy()) {
            $code .= "        lazy: true\n";
        }
        if ($definition->get_arguments()) {
            $code .= \sprintf("        arguments: %s\n", $this->dumper->dump($this->dump_value($definition->get_arguments()), 0));
        }
        if ($definition->get_properties()) {
            $code .= \sprintf("        properties: %s\n", $this->dumper->dump($this->dump_value($definition->get_properties()), 0));
        }
        if ($definition->get_method_calls()) {
            $code .= \sprintf("        calls:\n%s\n", $this->dumper->dump($this->dump_value($definition->get_method_calls()), 1, 12));
        }
        if (!$definition->is_shared()) {
            $code .= "        shared: false\n";
        }
        if (null !== $decorated_service = $definition->get_decorated_service()) {
            [$decorated, $renamed_id, $priority] = $decorated_service;
            $code .= \sprintf("        decorates: %s\n", $decorated);
            if (null !== $renamed_id) {
                $code .= \sprintf("        decoration_inner_name: %s\n", $renamed_id);
            }
            if (0 !== $priority) {
                $code .= \sprintf("        decoration_priority: %s\n", $priority);
            }
            $decoration_on_invalid = $decorated_service[3] ?? Container_Interface::EXCEPTION_ON_INVALID_REFERENCE;
            if (\in_array($decoration_on_invalid, [Container_Interface::IGNORE_ON_INVALID_REFERENCE, Container_Interface::NULL_ON_INVALID_REFERENCE], true)) {
                $invalid_behavior = Container_Interface::NULL_ON_INVALID_REFERENCE === $decoration_on_invalid ? 'null' : 'ignore';
                $code .= \sprintf("        decoration_on_invalid: %s\n", $invalid_behavior);
            }
        }
        if ($callable = $definition->get_factory()) {
            if (\is_array($callable) && ['Closure', 'fromCallable'] !== $callable && $definition->get_class() === $callable[0]) {
                $code .= \sprintf("        constructor: %s\n", $callable[1]);
            } else {
                $code .= \sprintf("        factory: %s\n", $this->dumper->dump($this->dump_callable($callable), 0));
            }
        }
        if ($callable = $definition->get_configurator()) {
            $code .= \sprintf("        configurator: %s\n", $this->dumper->dump($this->dump_callable($callable), 0));
        }
        return $code;
    }
    private function add_service_alias(string $alias, Alias $id): string
    {
        $deprecated = '';
        if ($id->is_deprecated()) {
            $deprecated = "        deprecated:\n";
            foreach ($id->get_deprecation('%alias_id%') as $key => $value) {
                if ('' !== $value) {
                    $deprecated .= \sprintf("            %s: %s\n", $key, $value);
                }
            }
        }
        if (!$id->is_deprecated() && $id->is_private()) {
            return \sprintf("    %s: '@%s'\n", $alias, $id);
        }
        if ($id->is_public()) {
            $deprecated = "        public: true\n" . $deprecated;
        }
        return \sprintf("    %s:\n        alias: %s\n%s", $alias, $id, $deprecated);
    }
    private function add_services(): string
    {
        if (!$this->container->get_definitions()) {
            return '';
        }
        $code = "services:\n";
        foreach ($this->container->get_definitions() as $id => $definition) {
            $code .= $this->add_service($id, $definition);
        }
        $aliases = $this->container->get_aliases();
        foreach ($aliases as $alias => $id) {
            while (isset($aliases[(string) $id])) {
                $id = $aliases[(string) $id];
            }
            $code .= $this->add_service_alias($alias, $id);
        }
        return $code;
    }
    private function add_parameters(): string
    {
        if (!$this->container->get_parameter_bag()->all()) {
            return '';
        }
        $parameters = $this->prepare_parameters($this->container->get_parameter_bag()->all(), $this->container->is_compiled());
        return $this->dumper->dump(['parameters' => $parameters], 2);
    }
    /**
     * Dumps callable to YAML format.
     */
    private function dump_callable(mixed $callable): mixed
    {
        if (\is_array($callable)) {
            if ($callable[0] instanceof Reference) {
                $callable = [$this->get_service_call((string) $callable[0], $callable[0]), $callable[1]];
            } else {
                $callable = [$callable[0], $callable[1]];
            }
        }
        return $this->container->resolve_env_placeholders($callable);
    }
    /**
     * Dumps the value to YAML format.
     *
     * @throws RuntimeException When trying to dump object or resource
     */
    private function dump_value(mixed $value): mixed
    {
        if ($value instanceof Service_Closure_Argument) {
            $value = $value->get_values()[0];
            return new Tagged_Value('service_closure', $this->dump_value($value));
        }
        if ($value instanceof Argument_Interface) {
            $tag = $value;
            if ($value instanceof Tagged_Iterator_Argument || $value instanceof Service_Locator_Argument && $tag = $value->get_tagged_iterator_argument()) {
                if (null === $tag->get_index_attribute()) {
                    $content = $tag->get_tag();
                } else {
                    $content = ['tag' => $tag->get_tag(), 'index_by' => $tag->get_index_attribute()];
                    if (null !== $tag->get_default_index_method(false)) {
                        $content['default_index_method'] = $tag->get_default_index_method(false);
                    }
                    if (null !== $tag->get_default_priority_method(false)) {
                        $content['default_priority_method'] = $tag->get_default_priority_method(false);
                    }
                }
                if ($excludes = $tag->get_exclude()) {
                    if (!\is_array($content)) {
                        $content = ['tag' => $content];
                    }
                    $content['exclude'] = 1 === \count($excludes) ? $excludes[0] : $excludes;
                }
                if (!$tag->exclude_self()) {
                    $content['exclude_self'] = false;
                }
                return new Tagged_Value($value instanceof Tagged_Iterator_Argument ? 'tagged_iterator' : 'tagged_locator', $content);
            }
            if ($value instanceof Iterator_Argument) {
                $tag = 'iterator';
            } elseif ($value instanceof Service_Locator_Argument) {
                $tag = 'service_locator';
            } else {
                throw new RuntimeException(\sprintf('Unspecified Yaml tag for type "%s".', get_debug_type($value)));
            }
            return new Tagged_Value($tag, $this->dump_value($value->get_values()));
        }
        if (\is_array($value)) {
            $code = [];
            foreach ($value as $k => $v) {
                $code[$this->container->resolve_env_placeholders($k)] = $this->dump_value($v);
            }
            return $code;
        }
        if ($value instanceof Reference) {
            return $this->get_service_call((string) $value, $value);
        }
        if ($value instanceof Parameter) {
            return $this->get_parameter_call((string) $value);
        }
        if ($value instanceof Expression) {
            return $this->get_expression_call((string) $value);
        }
        if ($value instanceof Definition) {
            return new Tagged_Value('service', (new Parser())->parse("_:\n" . $this->add_service('_', $value), Yaml::PARSE_CUSTOM_TAGS)['_']['_']);
        }
        if ($value instanceof \Unit_Enum) {
            return new Tagged_Value('php/enum', \sprintf('%s::%s', $value::class, $value->name));
        }
        if ($value instanceof Abstract_Argument) {
            return new Tagged_Value('abstract', $value->get_text());
        }
        if (\is_object($value) || \is_resource($value)) {
            throw new RuntimeException(\sprintf('Unable to dump a service container if a parameter is an object or a resource, got "%s".', get_debug_type($value)));
        }
        return $this->container->resolve_env_placeholders($value);
    }
    private function get_service_call(string $id, ?Reference $reference = null): string
    {
        if (null !== $reference) {
            switch ($reference->get_invalid_behavior()) {
                case Container_Interface::RUNTIME_EXCEPTION_ON_INVALID_REFERENCE:
                case Container_Interface::EXCEPTION_ON_INVALID_REFERENCE:
                    break;
                case Container_Interface::IGNORE_ON_UNINITIALIZED_REFERENCE:
                    return \sprintf('@!%s', $id);
                default:
                    return \sprintf('@?%s', $id);
            }
        }
        return \sprintf('@%s', $id);
    }
    private function get_parameter_call(string $id): string
    {
        return \sprintf('%%%s%%', $id);
    }
    private function get_expression_call(string $expression): string
    {
        return \sprintf('@=%s', $expression);
    }
    private function prepare_parameters(array $parameters, bool $escape = true): array
    {
        $filtered = [];
        foreach ($parameters as $key => $value) {
            if (\is_array($value)) {
                $value = $this->prepare_parameters($value, $escape);
            } elseif ($value instanceof Reference || \is_string($value) && str_starts_with($value, '@')) {
                $value = '@' . $value;
            }
            $filtered[$key] = $value;
        }
        return $escape ? $this->container->resolve_env_placeholders($this->escape($filtered)) : $filtered;
    }
    private function escape(array $arguments): array
    {
        $args = [];
        foreach ($arguments as $k => $v) {
            if (\is_array($v)) {
                $args[$k] = $this->escape($v);
            } elseif (\is_string($v)) {
                $args[$k] = str_replace('%', '%%', $v);
            } else {
                $args[$k] = $v;
            }
        }
        return $args;
    }
}