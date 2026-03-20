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
namespace Symfony\Component\Dependency_Injection\Lazy_Proxy\Php_Dumper;

use Symfony\Component\Dependency_Injection\Definition;
use Symfony\Component\Dependency_Injection\Exception\InvalidArgumentException;
use Symfony\Component\Var_Exporter\Exception\LogicException;
use Symfony\Component\Var_Exporter\Proxy_Helper;
/**
 * @author Nicolas Grekas <p@tchwork.com>
 */
final readonly class Lazy_Service_Dumper implements Dumper_Interface
{
    public function __construct(private string $salt = '')
    {
    }
    public function is_proxy_candidate(Definition $definition, ?bool &$as_ghost_object = null, ?string $id = null): bool
    {
        $as_ghost_object = false;
        if ($definition->has_tag('proxy')) {
            if (!$definition->is_lazy()) {
                throw new InvalidArgumentException(\sprintf('Invalid definition for service "%s": setting the "proxy" tag on a service requires it to be "lazy".', $id ?? $definition->get_class()));
            }
            return true;
        }
        if (!$definition->is_lazy()) {
            return false;
        }
        if (!($class = $definition->get_class()) || !(class_exists($class) || interface_exists($class, false))) {
            return false;
        }
        if ($definition->get_factory()) {
            return true;
        }
        foreach ($definition->get_method_calls() as $call) {
            if ($call[2] ?? false) {
                return true;
            }
        }
        try {
            $as_ghost_object = (bool) (new \ReflectionClass($class))->new_lazy_ghost(static fn(): null => null);
        } catch (\Error $e) {
            if (__FILE__ !== $e->get_file()) {
                throw $e;
            }
        }
        return true;
    }
    public function get_proxy_factory_code(Definition $definition, string $id, string $factory_code): string
    {
        $instantiation = 'return';
        if ($definition->is_shared()) {
            $instantiation .= \sprintf(' $container->%s[%s] =', $definition->is_public() ? 'services' : 'privates', var_export($id, true));
        }
        $as_ghost_object = str_contains($factory_code, '$proxy');
        $proxy_class = $this->get_proxy_class($definition, $as_ghost_object);
        if (!$as_ghost_object) {
            if ($definition->get_class() === $proxy_class) {
                return <<<EOF
                        if (true === \$lazyLoad) {
                            {$instantiation} new \\ReflectionClass('{$proxy_class}')->newLazyProxy(static fn () => {$factory_code});
                        }
                
                
                EOF;
            }
            return <<<EOF
                    if (true === \$lazyLoad) {
                        {$instantiation} \$container->createProxy('{$proxy_class}', static fn () => \\{$proxy_class}::createLazyProxy(static fn () => {$factory_code}));
                    }
            
            
            EOF;
        }
        $factory_code = \sprintf('static function ($proxy) use ($container) { %s; }', $factory_code);
        return <<<EOF
                if (true === \$lazyLoad) {
                    {$instantiation} new \\ReflectionClass('{$proxy_class}')->newLazyGhost({$factory_code});
                }
        
        
        EOF;
    }
    public function get_proxy_code(Definition $definition, ?string $id = null): string
    {
        if (!$this->is_proxy_candidate($definition, $as_ghost_object, $id)) {
            throw new InvalidArgumentException(\sprintf('Cannot instantiate lazy proxy for service "%s".', $id ?? $definition->get_class()));
        }
        $proxy_class = $this->get_proxy_class($definition, $as_ghost_object, $class);
        if ($as_ghost_object) {
            return '';
        }
        if ($definition->get_class() === $proxy_class) {
            return '';
        }
        $interfaces = [];
        if ($definition->has_tag('proxy')) {
            foreach ($definition->get_tag('proxy') as $tag) {
                if (!isset($tag['interface'])) {
                    throw new InvalidArgumentException(\sprintf('Invalid definition for service "%s": the "interface" attribute is missing on a "proxy" tag.', $id ?? $definition->get_class()));
                }
                if (!interface_exists($tag['interface']) && !class_exists($tag['interface'], false)) {
                    throw new InvalidArgumentException(\sprintf('Invalid definition for service "%s": several "proxy" tags found but "%s" is not an interface.', $id ?? $definition->get_class(), $tag['interface']));
                }
                if ('object' !== $definition->get_class() && !is_a($class->name, $tag['interface'], true)) {
                    throw new InvalidArgumentException(\sprintf('Invalid "proxy" tag for service "%s": class "%s" doesn\'t implement "%s".', $id ?? $definition->get_class(), $definition->get_class(), $tag['interface']));
                }
                $interfaces[] = new \ReflectionClass($tag['interface']);
            }
            $class = 1 === \count($interfaces) && !$interfaces[0]->is_interface() ? array_pop($interfaces) : null;
        } elseif ($class->is_interface()) {
            $interfaces = [$class];
            $class = null;
        }
        try {
            return ($class?->is_read_only() ? 'readonly ' : '') . 'class ' . $proxy_class . Proxy_Helper::generate_lazy_proxy($class, $interfaces);
        } catch (LogicException $e) {
            throw new InvalidArgumentException(\sprintf('Cannot generate lazy proxy for service "%s".', $id ?? $definition->get_class()), 0, $e);
        }
    }
    public function get_proxy_class(Definition $definition, bool $as_ghost_object, ?\ReflectionClass &$class = null): string
    {
        $class = 'object' !== $definition->get_class() ? $definition->get_class() : 'stdClass';
        $class = new \ReflectionClass($class);
        if ($as_ghost_object) {
            return $class->name;
        }
        if (!$definition->has_tag('proxy') && !$class->is_abstract()) {
            $parent = $class;
            do {
                $extends_internal_class = $parent->is_internal();
            } while (!$extends_internal_class && $parent = $parent->get_parent_class());
            if (!$extends_internal_class) {
                return $class->name;
            }
        }
        return preg_replace('/^.*\\\\/', '', (string) $definition->get_class()) . 'Proxy' . ucfirst(substr(hash('xxh128', $this->salt . '+' . $class->name . '+' . serialize($definition->get_tag('proxy'))), -7));
    }
}