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

use Symfony\Component\Dependency_Injection\Child_Definition;
use Symfony\Component\Dependency_Injection\Container_Interface;
use Symfony\Component\Dependency_Injection\Definition;
use Symfony\Component\Dependency_Injection\Exception\Exception_Interface;
use Symfony\Component\Dependency_Injection\Exception\RuntimeException;
use Symfony\Component\Dependency_Injection\Exception\Service_Circular_Reference_Exception;
/**
 * This replaces all ChildDefinition instances with their equivalent fully
 * merged Definition instance.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 * @author Nicolas Grekas <p@tchwork.com>
 */
class Resolve_Child_Definitions_Pass extends Abstract_Recursive_Pass
{
    protected bool $skip_scalars = true;
    private array $current_path;
    protected function process_value(mixed $value, bool $is_root = false): mixed
    {
        if (!$value instanceof Definition) {
            return parent::process_value($value, $is_root);
        }
        if ($is_root) {
            // yes, we are specifically fetching the definition from the
            // container to ensure we are not operating on stale data
            $value = $this->container->get_definition($this->current_id);
        }
        if ($value instanceof Child_Definition) {
            $this->current_path = [];
            $value = $this->resolve_definition($value);
            if ($is_root) {
                $this->container->set_definition($this->current_id, $value);
            }
        }
        return parent::process_value($value, $is_root);
    }
    /**
     * Resolves the definition.
     *
     * @throws RuntimeException When the definition is invalid
     */
    private function resolve_definition(Child_Definition $definition): Definition
    {
        try {
            return $this->do_resolve_definition($definition);
        } catch (Service_Circular_Reference_Exception $e) {
            throw $e;
        } catch (Exception_Interface $e) {
            $r = new \ReflectionProperty($e, 'message');
            $r->set_value($e, \sprintf('Service "%s": %s', $this->current_id, $e->get_message()));
            throw $e;
        }
    }
    private function do_resolve_definition(Child_Definition $definition): Definition
    {
        if (!$this->container->has($parent = $definition->get_parent())) {
            throw new RuntimeException(\sprintf('Parent definition "%s" does not exist.', $parent));
        }
        $search_key = array_search($parent, $this->current_path);
        $this->current_path[] = $parent;
        if (false !== $search_key) {
            throw new Service_Circular_Reference_Exception($parent, \array_slice($this->current_path, $search_key));
        }
        $parent_def = $this->container->find_definition($parent);
        if ($parent_def instanceof Child_Definition) {
            $id = $this->current_id;
            $this->current_id = $parent;
            $parent_def = $this->resolve_definition($parent_def);
            $this->container->set_definition($parent, $parent_def);
            $this->current_id = $id;
        }
        $this->container->log($this, \sprintf('Resolving inheritance for "%s" (parent: %s).', $this->current_id, $parent));
        $def = new Definition();
        // merge in parent definition
        // purposely ignored attributes: abstract, shared, tags, autoconfigured
        $def->set_class($parent_def->get_class());
        $def->set_arguments($parent_def->get_arguments());
        $def->set_method_calls($parent_def->get_method_calls());
        $def->set_properties($parent_def->get_properties());
        if ($parent_def->is_deprecated()) {
            $deprecation = $parent_def->get_deprecation('%service_id%');
            $def->set_deprecated($deprecation['package'], $deprecation['version'], $deprecation['message']);
        }
        $def->set_factory($parent_def->get_factory());
        $def->set_configurator($parent_def->get_configurator());
        $def->set_file($parent_def->get_file());
        $def->set_public($parent_def->is_public());
        $def->set_lazy($parent_def->is_lazy());
        $def->set_autowired($parent_def->is_autowired());
        $def->set_changes($parent_def->get_changes());
        $def->set_bindings($definition->get_bindings() + $parent_def->get_bindings());
        $def->set_synthetic($definition->is_synthetic());
        // overwrite with values specified in the decorator
        $changes = $definition->get_changes();
        if (isset($changes['class'])) {
            $def->set_class($definition->get_class());
        }
        if (isset($changes['factory'])) {
            $def->set_factory($definition->get_factory());
        }
        if (isset($changes['configurator'])) {
            $def->set_configurator($definition->get_configurator());
        }
        if (isset($changes['file'])) {
            $def->set_file($definition->get_file());
        }
        if (isset($changes['public'])) {
            $def->set_public($definition->is_public());
        } else {
            $def->set_public($parent_def->is_public());
        }
        if (isset($changes['lazy'])) {
            $def->set_lazy($definition->is_lazy());
        }
        if (isset($changes['deprecated']) && $definition->is_deprecated()) {
            $deprecation = $definition->get_deprecation('%service_id%');
            $def->set_deprecated($deprecation['package'], $deprecation['version'], $deprecation['message']);
        }
        if (isset($changes['autowired'])) {
            $def->set_autowired($definition->is_autowired());
        }
        if (isset($changes['shared'])) {
            $def->set_shared($definition->is_shared());
        }
        if (isset($changes['decorated_service'])) {
            $decorated_service = $definition->get_decorated_service();
            if (null === $decorated_service) {
                $def->set_decorated_service($decorated_service);
            } else {
                $def->set_decorated_service($decorated_service[0], $decorated_service[1], $decorated_service[2], $decorated_service[3] ?? Container_Interface::EXCEPTION_ON_INVALID_REFERENCE);
            }
        }
        // merge arguments
        foreach ($definition->get_arguments() as $k => $v) {
            if (is_numeric($k)) {
                $def->add_argument($v);
            } elseif (str_starts_with($k, 'index_')) {
                $def->replace_argument((int) substr($k, \strlen('index_')), $v);
            } else {
                $def->set_argument($k, $v);
            }
        }
        // merge properties
        foreach ($definition->get_properties() as $k => $v) {
            $def->set_property($k, $v);
        }
        // append method calls
        if ($calls = $definition->get_method_calls()) {
            $def->set_method_calls(array_merge($def->get_method_calls(), $calls));
        }
        $def->add_error($parent_def);
        $def->add_error($definition);
        // these attributes are always taken from the child
        $def->set_abstract($definition->is_abstract());
        $def->set_tags($definition->get_tags());
        // autoconfigure is never taken from parent (on purpose)
        // and it's not legal on an instanceof
        $def->set_autoconfigured($definition->is_autoconfigured());
        if (!$def->has_tag('proxy')) {
            foreach ($parent_def->get_tag('proxy') as $v) {
                $def->add_tag('proxy', $v);
            }
        }
        return $def;
    }
}