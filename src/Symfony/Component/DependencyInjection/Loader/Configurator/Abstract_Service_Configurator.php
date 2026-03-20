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

use Symfony\Component\Dependency_Injection\Definition;
use Symfony\Component\Dependency_Injection\Exception\Service_Not_Found_Exception;
abstract class Abstract_Service_Configurator extends Abstract_Configurator
{
    public function __construct(protected Services_Configurator $parent, Definition $definition, protected ?string $id = null, private array $default_tags = [])
    {
        $this->definition = $definition;
    }
    public function __destruct()
    {
        // default tags should be added last
        foreach ($this->default_tags as $name => $attributes) {
            foreach ($attributes as $attribute) {
                $this->definition->add_tag($name, $attribute);
            }
        }
        $this->default_tags = [];
    }
    /**
     * Registers a service.
     */
    final public function set(?string $id, ?string $class = null): Service_Configurator
    {
        $this->__destruct();
        return $this->parent->set($id, $class);
    }
    /**
     * Creates an alias.
     */
    final public function alias(string $id, string $referenced_id): Alias_Configurator
    {
        $this->__destruct();
        return $this->parent->alias($id, $referenced_id);
    }
    /**
     * Registers a PSR-4 namespace using a glob pattern.
     */
    final public function load(string $namespace, string $resource): Prototype_Configurator
    {
        $this->__destruct();
        return $this->parent->load($namespace, $resource);
    }
    /**
     * Gets an already defined service definition.
     *
     * @throws ServiceNotFoundException if the service definition does not exist
     */
    final public function get(string $id): Service_Configurator
    {
        $this->__destruct();
        return $this->parent->get($id);
    }
    /**
     * Removes an already defined service definition or alias.
     */
    final public function remove(string $id): Services_Configurator
    {
        $this->__destruct();
        return $this->parent->remove($id);
    }
    /**
     * Registers a stack of decorator services.
     *
     * @param InlineServiceConfigurator[]|ReferenceConfigurator[] $services
     */
    final public function stack(string $id, array $services): Alias_Configurator
    {
        $this->__destruct();
        return $this->parent->stack($id, $services);
    }
    /**
     * Registers a service.
     */
    final public function __invoke(string $id, ?string $class = null): Service_Configurator
    {
        $this->__destruct();
        return $this->parent->set($id, $class);
    }
}