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
namespace Symfony\Bridge\Doctrine\Dependency_Injection\Compiler_Pass;

use Symfony\Component\Dependency_Injection\Compiler\Compiler_Pass_Interface;
use Symfony\Component\Dependency_Injection\Container_Builder;
use Symfony\Component\Dependency_Injection\Definition;
use Symfony\Component\Dependency_Injection\Exception\InvalidArgumentException;
use Symfony\Component\Dependency_Injection\Reference;
/**
 * Base class for the doctrine bundles to provide a compiler pass class that
 * helps to register doctrine mappings.
 *
 * The compiler pass is meant to register the mappings with the metadata
 * chain driver corresponding to one of the object managers.
 *
 * For concrete implementations, see the RegisterXyMappingsPass classes
 * in the DoctrineBundle resp.
 * DoctrineMongodbBundle, DoctrineCouchdbBundle and DoctrinePhpcrBundle.
 *
 * @author David Buchmann <david@liip.ch>
 */
abstract class Register_Mappings_Pass implements Compiler_Pass_Interface
{
    /**
     * The $managerParameters is an ordered list of container parameters that could provide the
     * name of the manager to register these namespaces and alias on. The first non-empty name
     * is used, the others skipped.
     *
     * The $aliasMap parameter can be used to define bundle namespace shortcuts like the
     * DoctrineBundle provides automatically for objects in the default Entity/Document folder.
     *
     * @param Definition|Reference $driver                  Driver DI definition or reference
     * @param string[]             $namespaces              List of namespaces handled by $driver
     * @param string[]             $managerParameters       list of container parameters that could
     *                                                      hold the manager name
     * @param string               $driverPattern           Pattern for the metadata chain driver service ids (e.g. "doctrine.orm.%s_metadata_driver")
     * @param string|false         $enabledParameter        Service container parameter that must be
     *                                                      present to enable the mapping (regardless of the
     *                                                      parameter value). Pass false to not do any check.
     * @param string               $configurationPattern    Pattern for the Configuration service name,
     *                                                      for example 'doctrine.orm.%s_configuration'.
     * @param string               $registerAliasMethodName Method name to call on the configuration service. This
     *                                                      depends on the Doctrine implementation.
     *                                                      For example addEntityNamespace.
     * @param string[]             $aliasMap                Map of alias to namespace
     */
    public function __construct(protected Definition|Reference $driver, protected array $namespaces, protected array $manager_parameters, protected string $driver_pattern, protected string|false $enabled_parameter = false, private readonly string $configuration_pattern = '', private readonly string $register_alias_method_name = '', private readonly array $alias_map = [])
    {
        if ($alias_map) {
            trigger_deprecation('symfony/doctrine-bridge', '8.1', 'The property RegisterMappingsPass::$aliasMap is deprecated and will be removed in 9.0. Namespace alias are no longer supported.');
            if (!$configuration_pattern || !$register_alias_method_name) {
                throw new \InvalidArgumentException('configurationPattern and registerAliasMethodName are required to register namespace alias.');
            }
        }
    }
    /**
     * Register mappings and alias with the metadata drivers.
     */
    public function process(Container_Builder $container): void
    {
        if (!$this->enabled($container)) {
            return;
        }
        $mapping_driver_def = $this->get_driver($container);
        $chain_driver_def_service = $this->get_chain_driver_service_name($container);
        // Definition for a Doctrine\Persistence\Mapping\Driver\MappingDriverChain
        $chain_driver_def = $container->get_definition($chain_driver_def_service);
        foreach ($this->namespaces as $namespace) {
            $chain_driver_def->add_method_call('addDriver', [$mapping_driver_def, $namespace]);
        }
        if (!\count($this->alias_map)) {
            return;
        }
        $configuration_service_name = $this->get_configuration_service_name($container);
        // Definition of the Doctrine\...\Configuration class specific to the Doctrine flavour.
        $configuration_service_definition = $container->get_definition($configuration_service_name);
        foreach ($this->alias_map as $alias => $namespace) {
            $configuration_service_definition->add_method_call($this->register_alias_method_name, [$alias, $namespace]);
        }
    }
    /**
     * Get the service name of the metadata chain driver that the mappings
     * should be registered with.
     *
     * @throws InvalidArgumentException if none of the managerParameters has a
     *                                  non-empty value
     */
    protected function get_chain_driver_service_name(Container_Builder $container): string
    {
        return \sprintf($this->driver_pattern, $this->get_manager_name($container));
    }
    /**
     * Create the service definition for the metadata driver.
     *
     * @param ContainerBuilder $container Passed on in case an extending class
     *                                    needs access to the container
     */
    protected function get_driver(Container_Builder $container): Definition|Reference
    {
        return $this->driver;
    }
    /**
     * Get the service name from the pattern and the configured manager name.
     *
     * @throws InvalidArgumentException if none of the managerParameters has a
     *                                  non-empty value
     */
    private function get_configuration_service_name(Container_Builder $container): string
    {
        return \sprintf($this->configuration_pattern, $this->get_manager_name($container));
    }
    /**
     * Determine the manager name.
     *
     * The default implementation loops over the managerParameters and returns
     * the first non-empty parameter.
     *
     * @throws InvalidArgumentException if none of the managerParameters is found in the container
     */
    private function get_manager_name(Container_Builder $container): string
    {
        foreach ($this->manager_parameters as $param) {
            if ($container->has_parameter($param)) {
                $name = $container->get_parameter($param);
                if ($name) {
                    return $name;
                }
            }
        }
        throw new InvalidArgumentException(\sprintf('Could not find the manager name parameter in the container. Tried the following parameter names: "%s".', implode('", "', $this->manager_parameters)));
    }
    /**
     * Determine whether this mapping should be activated or not. This allows
     * to take this decision with the container builder available.
     *
     * This default implementation checks if the class has the enabledParameter
     * configured and if so if that parameter is present in the container.
     */
    protected function enabled(Container_Builder $container): bool
    {
        return !$this->enabled_parameter || $container->has_parameter($this->enabled_parameter);
    }
}