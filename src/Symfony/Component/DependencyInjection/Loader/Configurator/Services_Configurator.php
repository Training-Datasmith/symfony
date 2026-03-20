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

use Symfony\Component\Dependency_Injection\Alias;
use Symfony\Component\Dependency_Injection\Child_Definition;
use Symfony\Component\Dependency_Injection\Container_Builder;
use Symfony\Component\Dependency_Injection\Definition;
use Symfony\Component\Dependency_Injection\Exception\InvalidArgumentException;
use Symfony\Component\Dependency_Injection\Exception\Service_Not_Found_Exception;
use Symfony\Component\Dependency_Injection\Loader\Php_File_Loader;
/**
 * @author Nicolas Grekas <p@tchwork.com>
 */
class Services_Configurator extends Abstract_Configurator
{
    public const FACTORY = 'services';
    private Definition $defaults;
    private array $instanceof;
    private readonly string $anonymous_hash;
    private int $anonymous_count;
    public function __construct(private readonly Container_Builder $container, private readonly Php_File_Loader $loader, array &$instanceof, private readonly ?string $path = null, int &$anonymous_count = 0)
    {
        $this->defaults = new Definition();
        $this->instanceof =& $instanceof;
        $this->anonymous_hash = Container_Builder::hash($path ?: mt_rand());
        $this->anonymous_count =& $anonymous_count;
        $instanceof = [];
    }
    /**
     * Defines a set of defaults for following service definitions.
     */
    final public function defaults(): Defaults_Configurator
    {
        return new Defaults_Configurator($this, $this->defaults = new Definition(), $this->path);
    }
    /**
     * Defines an instanceof-conditional to be applied to following service definitions.
     */
    final public function instanceof(string $fqcn): Instanceof_Configurator
    {
        $this->instanceof[$fqcn] = $definition = new Child_Definition('');
        return new Instanceof_Configurator($this, $definition, $fqcn, $this->path);
    }
    /**
     * Registers a service.
     *
     * @param string|null $id    The service id, or null to create an anonymous service
     * @param string|null $class The class of the service, or null when $id is also the class name
     */
    final public function set(?string $id, ?string $class = null): Service_Configurator
    {
        $defaults = $this->defaults;
        $definition = new Definition();
        if (null === $id) {
            if (!$class) {
                throw new \LogicException('Anonymous services must have a class name.');
            }
            $id = \sprintf('.%d_%s', ++$this->anonymous_count, preg_replace('/^.*\\\\/', '', $class) . '~' . $this->anonymous_hash);
        } else {
            $definition->set_public($defaults->is_public());
        }
        $definition->set_autowired($defaults->is_autowired());
        $definition->set_autoconfigured($defaults->is_autoconfigured());
        // deep clone, to avoid multiple process of the same instance in the passes
        $definition->set_bindings(unserialize(serialize($defaults->get_bindings())));
        $definition->set_changes([]);
        $configurator = new Service_Configurator($this->container, $this->instanceof, true, $this, $definition, $id, $defaults->get_tags(), $this->path);
        return null !== $class ? $configurator->class($class) : $configurator;
    }
    /**
     * Removes an already defined service definition or alias.
     *
     * @return $this
     */
    final public function remove(string $id): static
    {
        $this->container->remove_definition($id);
        $this->container->remove_alias($id);
        return $this;
    }
    /**
     * Creates an alias.
     */
    final public function alias(string $id, string $referenced_id): Alias_Configurator
    {
        $ref = static::process_value($referenced_id, true);
        $alias = new Alias((string) $ref);
        $alias->set_public($this->defaults->is_public());
        $this->container->set_alias($id, $alias);
        return new Alias_Configurator($this, $alias);
    }
    /**
     * Registers a PSR-4 namespace using a glob pattern.
     */
    final public function load(string $namespace, string $resource): Prototype_Configurator
    {
        return new Prototype_Configurator($this, $this->loader, $this->defaults, $namespace, $resource, true, $this->path);
    }
    /**
     * Gets an already defined service definition.
     *
     * @throws ServiceNotFoundException if the service definition does not exist
     */
    final public function get(string $id): Service_Configurator
    {
        $definition = $this->container->get_definition($id);
        return new Service_Configurator($this->container, $definition->get_instanceof_conditionals(), true, $this, $definition, $id, []);
    }
    /**
     * Registers a stack of decorator services.
     *
     * @param InlineServiceConfigurator[]|ReferenceConfigurator[] $services
     */
    final public function stack(string $id, array $services): Alias_Configurator
    {
        foreach ($services as $i => $service) {
            if ($service instanceof Inline_Service_Configurator) {
                $definition = $service->definition->set_instanceof_conditionals($this->instanceof);
                $changes = $definition->get_changes();
                $definition->set_autowired((isset($changes['autowired']) ? $definition : $this->defaults)->is_autowired());
                $definition->set_autoconfigured((isset($changes['autoconfigured']) ? $definition : $this->defaults)->is_autoconfigured());
                $definition->set_bindings(array_merge($this->defaults->get_bindings(), $definition->get_bindings()));
                $definition->set_changes($changes);
                $services[$i] = $definition;
            } elseif (!$service instanceof Reference_Configurator) {
                throw new InvalidArgumentException(\sprintf('"%s()" expects a list of definitions as returned by "%s()" or "%s()", "%s" given at index "%s" for service "%s".', __METHOD__, Inline_Service_Configurator::FACTORY, Reference_Configurator::FACTORY, $service instanceof Abstract_Configurator ? $service::FACTORY . '()' : get_debug_type($service), $i, $id));
            }
        }
        $alias = $this->alias($id, '');
        $alias->definition = $this->set($id)->parent('')->args($services)->tag('container.stack')->definition;
        return $alias;
    }
    /**
     * Registers a service.
     */
    final public function __invoke(string $id, ?string $class = null): Service_Configurator
    {
        return $this->set($id, $class);
    }
    public function __destruct()
    {
        $this->loader->register_aliases_for_singly_implemented_interfaces();
    }
}