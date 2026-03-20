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
use Symfony\Component\Dependency_Injection\Exception\RuntimeException;
use Symfony\Component\Dependency_Injection\Parameter;
use Symfony\Component\Dependency_Injection\Reference;
use Symfony\Component\Expression_Language\Expression;
/**
 * XmlDumper dumps a service container as an XML string.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Martin Hasoň <martin.hason@gmail.com>
 */
class Xml_Dumper extends Dumper
{
    /**
     * Dumps the service container as an XML string.
     */
    public function dump(array $options = []): string
    {
        $xml = <<<EOXML
        <?xml version="1.0" encoding="utf-8"?>
        <container xmlns="http://symfony.com/schema/dic/services" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="http://symfony.com/schema/dic/services https://symfony.com/schema/dic/services/services-1.0.xsd">
        EOXML;
        foreach ($this->add_parameters() as $line) {
            $xml .= "\n  " . $line;
        }
        foreach ($this->add_services() as $line) {
            $xml .= "\n  " . $line;
        }
        $xml .= "\n</container>\n";
        return $this->container->resolve_env_placeholders($xml);
    }
    private function add_parameters(): iterable
    {
        if (!$data = $this->container->get_parameter_bag()->all()) {
            return;
        }
        if ($this->container->is_compiled()) {
            $data = $this->escape($data);
        }
        yield '<parameters>';
        foreach ($this->convert_parameters($data, 'parameter') as $line) {
            yield '  ' . $line;
        }
        yield '</parameters>';
    }
    private function add_method_calls(array $methodcalls): iterable
    {
        foreach ($methodcalls as $methodcall) {
            $xml_attr = \sprintf(' method="%s"%s', $this->encode($methodcall[0]), $methodcall[2] ?? false ? ' returns-clone="true"' : '');
            if ($methodcall[1]) {
                yield \sprintf('<call%s>', $xml_attr);
                foreach ($this->convert_parameters($methodcall[1], 'argument') as $line) {
                    yield '  ' . $line;
                }
                yield '</call>';
            } else {
                yield \sprintf('<call%s/>', $xml_attr);
            }
        }
    }
    private function add_service(Definition $definition, ?string $id): iterable
    {
        $xml_attr = '';
        if (null !== $id) {
            $xml_attr .= \sprintf(' id="%s"', $this->encode($id));
        }
        if ($class = $definition->get_class()) {
            if (str_starts_with($class, '\\')) {
                $class = substr($class, 1);
            }
            $xml_attr .= \sprintf(' class="%s"', $this->encode($class));
        }
        if (!$definition->is_shared()) {
            $xml_attr .= ' shared="false"';
        }
        if ($definition->is_public()) {
            $xml_attr .= ' public="true"';
        }
        if ($definition->is_synthetic()) {
            $xml_attr .= ' synthetic="true"';
        }
        if ($definition->is_lazy()) {
            $xml_attr .= ' lazy="true"';
        }
        if (null !== $decorated_service = $definition->get_decorated_service()) {
            [$decorated, $renamed_id, $priority] = $decorated_service;
            $xml_attr .= \sprintf(' decorates="%s"', $this->encode($decorated));
            $decoration_on_invalid = $decorated_service[3] ?? Container_Interface::EXCEPTION_ON_INVALID_REFERENCE;
            if (\in_array($decoration_on_invalid, [Container_Interface::IGNORE_ON_INVALID_REFERENCE, Container_Interface::NULL_ON_INVALID_REFERENCE], true)) {
                $invalid_behavior = Container_Interface::NULL_ON_INVALID_REFERENCE === $decoration_on_invalid ? 'null' : 'ignore';
                $xml_attr .= \sprintf(' decoration-on-invalid="%s"', $invalid_behavior);
            }
            if (null !== $renamed_id) {
                $xml_attr .= \sprintf(' decoration-inner-name="%s"', $this->encode($renamed_id));
            }
            if (0 !== $priority) {
                $xml_attr .= \sprintf(' decoration-priority="%d"', $priority);
            }
        }
        $xml = [];
        $tags = $definition->get_tags();
        $tags['container.error'] = array_map(static fn($e): array => ['message' => $e], $definition->get_errors());
        foreach ($tags as $name => $tags) {
            foreach ($tags as $attributes) {
                // Check if we have recursive attributes
                if (array_filter($attributes, \is_array(...))) {
                    $xml[] = \sprintf('  <tag name="%s">', $this->encode($name));
                    foreach ($this->add_tag_recursive_attributes($attributes) as $line) {
                        $xml[] = '    ' . $line;
                    }
                    $xml[] = '  </tag>';
                } else {
                    $has_name_attr = \array_key_exists('name', $attributes);
                    $attr = \sprintf(' name="%s"', $this->encode($has_name_attr ? $attributes['name'] : $name));
                    foreach ($attributes as $key => $value) {
                        if ('name' !== $key) {
                            $attr .= \sprintf(' %s="%s"', $this->encode($key), $this->encode(self::php_to_xml($value ?? '')));
                        }
                    }
                    if ($has_name_attr) {
                        $xml[] = \sprintf('  <tag%s>%s</tag>', $attr, $this->encode($name, 0));
                    } else {
                        $xml[] = \sprintf('  <tag%s/>', $attr);
                    }
                }
            }
        }
        if ($definition->get_file()) {
            $xml[] = \sprintf('  <file>%s</file>', $this->encode($definition->get_file(), 0));
        }
        foreach ($this->convert_parameters($definition->get_arguments(), 'argument') as $line) {
            $xml[] = '  ' . $line;
        }
        foreach ($this->convert_parameters($definition->get_properties(), 'property', 'name') as $line) {
            $xml[] = '  ' . $line;
        }
        foreach ($this->add_method_calls($definition->get_method_calls()) as $line) {
            $xml[] = '  ' . $line;
        }
        if ($callable = $definition->get_factory()) {
            if (\is_array($callable) && ['Closure', 'fromCallable'] !== $callable && $definition->get_class() === $callable[0]) {
                $xml_attr .= \sprintf(' constructor="%s"', $this->encode($callable[1]));
            } else if (\is_array($callable) && $callable[0] instanceof Definition) {
                $xml[] = \sprintf('  <factory method="%s">', $this->encode($callable[1]));
                foreach ($this->add_service($callable[0], null) as $line) {
                    $xml[] = '    ' . $line;
                }
                $xml[] = '  </factory>';
            } elseif (\is_array($callable)) {
                if (null !== $callable[0]) {
                    $xml[] = \sprintf('  <factory %s="%s" method="%s"/>', $callable[0] instanceof Reference ? 'service' : 'class', $this->encode($callable[0]), $this->encode($callable[1]));
                } else {
                    $xml[] = \sprintf('  <factory method="%s"/>', $this->encode($callable[1]));
                }
            } else {
                $xml[] = \sprintf('  <factory function="%s"/>', $this->encode($callable));
            }
        }
        if ($definition->is_deprecated()) {
            $deprecation = $definition->get_deprecation('%service_id%');
            $xml[] = \sprintf('  <deprecated package="%s" version="%s">%s</deprecated>', $this->encode($deprecation['package']), $this->encode($deprecation['version']), $this->encode($deprecation['message'], 0));
        }
        if ($definition->is_autowired()) {
            $xml_attr .= ' autowire="true"';
        }
        if ($definition->is_autoconfigured()) {
            $xml_attr .= ' autoconfigure="true"';
        }
        if ($definition->is_abstract()) {
            $xml_attr .= ' abstract="true"';
        }
        if ($callable = $definition->get_configurator()) {
            if (\is_array($callable) && $callable[0] instanceof Definition) {
                $xml[] = \sprintf('  <configurator method="%s">', $this->encode($callable[1]));
                foreach ($this->add_service($callable[0], null) as $line) {
                    $xml[] = '    ' . $line;
                }
                $xml[] = '  </configurator>';
            } elseif (\is_array($callable)) {
                $xml[] = \sprintf('  <configurator %s="%s" method="%s"/>', $callable[0] instanceof Reference ? 'service' : 'class', $this->encode($callable[0]), $this->encode($callable[1]));
            } else {
                $xml[] = \sprintf('  <configurator function="%s"/>', $this->encode($callable));
            }
        }
        if (!$xml) {
            yield \sprintf('<service%s/>', $xml_attr);
        } else {
            yield \sprintf('<service%s>', $xml_attr);
            yield from $xml;
            yield '</service>';
        }
    }
    private function add_service_alias(string $alias, Alias $id): iterable
    {
        $xml_attr = \sprintf(' id="%s" alias="%s"%s', $this->encode($alias), $this->encode($id), $id->is_public() ? ' public="true"' : '');
        if ($id->is_deprecated()) {
            $deprecation = $id->get_deprecation('%alias_id%');
            yield \sprintf('<service%s>', $xml_attr);
            yield \sprintf('  <deprecated package="%s" version="%s">%s</deprecated>', $this->encode($deprecation['package']), $this->encode($deprecation['version']), $this->encode($deprecation['message'], 0));
            yield '</service>';
        } else {
            yield \sprintf('<service%s/>', $xml_attr);
        }
    }
    private function add_services(): iterable
    {
        if (!$definitions = $this->container->get_definitions()) {
            return;
        }
        yield '<services>';
        foreach ($definitions as $id => $definition) {
            foreach ($this->add_service($definition, $id) as $line) {
                yield '  ' . $line;
            }
        }
        $aliases = $this->container->get_aliases();
        foreach ($aliases as $alias => $id) {
            while (isset($aliases[(string) $id])) {
                $id = $aliases[(string) $id];
            }
            foreach ($this->add_service_alias($alias, $id) as $line) {
                yield '  ' . $line;
            }
        }
        yield '</services>';
    }
    private function add_tag_recursive_attributes(array $attributes): iterable
    {
        foreach ($attributes as $name => $value) {
            if (\is_array($value)) {
                yield \sprintf('<attribute name="%s">', $this->encode($name));
                foreach ($this->add_tag_recursive_attributes($value) as $line) {
                    yield '  ' . $line;
                }
                yield '</attribute>';
            } elseif ('' !== $value = self::php_to_xml($value ?? '')) {
                yield \sprintf('<attribute name="%s">%s</attribute>', $this->encode($name), $this->encode($value, 0));
            }
        }
    }
    private function convert_parameters(array $parameters, string $type, string $key_attribute = 'key'): iterable
    {
        $with_keys = !array_is_list($parameters);
        foreach ($parameters as $key => $value) {
            $xml_attr = $with_keys ? \sprintf(' %s="%s"', $key_attribute, $this->encode($key)) : '';
            if ($value instanceof Tagged_Iterator_Argument && ($tag = $value) || $value instanceof Service_Locator_Argument && $tag = $value->get_tagged_iterator_argument()) {
                $xml_attr .= \sprintf(' type="%s"', $value instanceof Tagged_Iterator_Argument ? 'tagged_iterator' : 'tagged_locator');
                $xml_attr .= \sprintf(' tag="%s"', $this->encode($tag->get_tag()));
                if (null !== $tag->get_index_attribute()) {
                    $xml_attr .= \sprintf(' index-by="%s"', $this->encode($tag->get_index_attribute()));
                    $default_prefix = 'getDefault' . str_replace(' ', '', ucwords((string) preg_replace('/[^a-zA-Z0-9\x7f-\xff]++/', ' ', $tag->get_index_attribute())));
                    if ($tag->get_default_index_method(false) !== $default_prefix . 'Name') {
                        $xml_attr .= \sprintf(' default-index-method="%s"', $this->encode($tag->get_default_index_method(false)));
                    }
                    if ($tag->get_default_priority_method(false) !== $default_prefix . 'Priority') {
                        $xml_attr .= \sprintf(' default-priority-method="%s"', $this->encode($tag->get_default_priority_method(false)));
                    }
                }
                if (1 === \count($excludes = $tag->get_exclude())) {
                    $xml_attr .= \sprintf(' exclude="%s"', $this->encode($excludes[0]));
                }
                if (!$tag->exclude_self()) {
                    $xml_attr .= ' exclude-self="false"';
                }
                if (1 < \count($excludes)) {
                    yield \sprintf('<%s%s>', $type, $xml_attr);
                    foreach ($excludes as $exclude) {
                        yield \sprintf('  <exclude>%s</exclude>', $this->encode($exclude, 0));
                    }
                    yield \sprintf('</%s>', $type);
                } else {
                    yield \sprintf('<%s%s/>', $type, $xml_attr);
                }
            } elseif (match (true) {
                \is_array($value) && $xml_attr .= ' type="collection"' => true,
                $value instanceof Iterator_Argument && $xml_attr .= ' type="iterator"' => true,
                $value instanceof Service_Locator_Argument && $xml_attr .= ' type="service_locator"' => true,
                $value instanceof Service_Closure_Argument && !$value->get_values()[0] instanceof Reference && $xml_attr .= ' type="service_closure"' => true,
                default => false,
            }) {
                if ($value instanceof Argument_Interface) {
                    $value = $value->get_values();
                }
                if ($value) {
                    yield \sprintf('<%s%s>', $type, $xml_attr);
                    foreach ($this->convert_parameters($value, $type, 'key') as $line) {
                        yield '  ' . $line;
                    }
                    yield \sprintf('</%s>', $type);
                } else {
                    yield \sprintf('<%s%s/>', $type, $xml_attr);
                }
            } elseif ($value instanceof Reference || $value instanceof Service_Closure_Argument) {
                if ($value instanceof Service_Closure_Argument) {
                    $xml_attr .= ' type="service_closure"';
                    $value = $value->get_values()[0];
                } else {
                    $xml_attr .= ' type="service"';
                }
                $xml_attr .= \sprintf(' id="%s"', $this->encode((string) $value));
                $xml_attr .= match ($value->get_invalid_behavior()) {
                    Container_Interface::NULL_ON_INVALID_REFERENCE => ' on-invalid="null"',
                    Container_Interface::IGNORE_ON_INVALID_REFERENCE => ' on-invalid="ignore"',
                    Container_Interface::IGNORE_ON_UNINITIALIZED_REFERENCE => ' on-invalid="ignore_uninitialized"',
                    default => '',
                };
                yield \sprintf('<%s%s/>', $type, $xml_attr);
            } elseif ($value instanceof Definition) {
                $xml_attr .= ' type="service"';
                yield \sprintf('<%s%s>', $type, $xml_attr);
                foreach ($this->add_service($value, null) as $line) {
                    yield '  ' . $line;
                }
                yield \sprintf('</%s>', $type);
            } else {
                if ($value instanceof Expression) {
                    $xml_attr .= ' type="expression"';
                    $value = (string) $value;
                } elseif (\is_string($value) && !preg_match('/^[^\x00-\x08\x0B\x0C\x0E-\x1F\x7F]*+$/u', $value)) {
                    $xml_attr .= ' type="binary"';
                    $value = base64_encode($value);
                } elseif ($value instanceof \Unit_Enum) {
                    $xml_attr .= ' type="constant"';
                } elseif ($value instanceof Abstract_Argument) {
                    $xml_attr .= ' type="abstract"';
                    $value = $value->get_text();
                } elseif (\in_array($value, ['null', 'true', 'false'], true)) {
                    $xml_attr .= ' type="string"';
                } elseif (\is_string($value) && (is_numeric($value) || preg_match('/^0b[01]*$/', $value) || preg_match('/^0x[0-9a-f]++$/i', $value))) {
                    $xml_attr .= ' type="string"';
                }
                if ('' === $value = self::php_to_xml($value)) {
                    yield \sprintf('<%s%s/>', $type, $xml_attr);
                } else {
                    yield \sprintf('<%s%s>%s</%1$s>', $type, $xml_attr, $this->encode($value, 0));
                }
            }
        }
    }
    private function encode(string $value, int $flags = \ENT_COMPAT): string
    {
        return str_replace("\r", '&#13;', htmlspecialchars($value, \ENT_XML1 | \ENT_SUBSTITUTE | $flags, 'UTF-8'));
    }
    private function escape(array $arguments): array
    {
        $args = [];
        foreach ($arguments as $k => $v) {
            $args[$k] = match (true) {
                \is_array($v) => $this->escape($v),
                \is_string($v) => str_replace('%', '%%', $v),
                default => $v,
            };
        }
        return $args;
    }
    /**
     * Converts php types to xml types.
     *
     * @throws RuntimeException When trying to dump object or resource
     */
    public static function php_to_xml(mixed $value): string
    {
        return match (true) {
            null === $value => 'null',
            true === $value => 'true',
            false === $value => 'false',
            $value instanceof Parameter => '%' . $value . '%',
            $value instanceof \Unit_Enum => \sprintf('%s::%s', $value::class, $value->name),
            \is_object($value), \is_resource($value) => throw new RuntimeException(\sprintf('Unable to dump a service container if a parameter is an object or a resource, got "%s".', get_debug_type($value))),
            default => (string) $value,
        };
    }
}