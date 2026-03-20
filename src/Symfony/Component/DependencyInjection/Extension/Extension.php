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
namespace Symfony\Component\Dependency_Injection\Extension;

use Symfony\Component\Config\Definition\Configuration_Interface;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\Dependency_Injection\Container;
use Symfony\Component\Dependency_Injection\Container_Builder;
use Symfony\Component\Dependency_Injection\Exception\BadMethodCallException;
use Symfony\Component\Dependency_Injection\Exception\InvalidArgumentException;
use Symfony\Component\Dependency_Injection\Exception\LogicException;
/**
 * Provides useful features shared by many extensions.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
abstract class Extension implements Extension_Interface, Configuration_Extension_Interface
{
    private array $processed_configs = [];
    /**
     * Returns the recommended alias to use in XML.
     *
     * This alias is also the mandatory prefix to use when using YAML.
     *
     * This convention is to remove the "Extension" postfix from the class
     * name and then lowercase and underscore the result. So:
     *
     *     AcmeHelloExtension
     *
     * becomes
     *
     *     acme_hello
     *
     * This can be overridden in a sub-class to specify the alias manually.
     *
     * @throws BadMethodCallException When the extension name does not follow conventions
     */
    public function get_alias(): string
    {
        $class_name = static::class;
        if (!str_ends_with($class_name, 'Extension')) {
            throw new BadMethodCallException('This extension does not follow the naming convention; you must overwrite the getAlias() method.');
        }
        $class_base_name = substr(strrchr($class_name, '\\'), 1, -9);
        return Container::underscore($class_base_name);
    }
    public function get_configuration(array $config, Container_Builder $container): ?Configuration_Interface
    {
        $class = static::class;
        if (str_contains($class, "\x00")) {
            return null;
            // ignore anonymous classes
        }
        $class = substr_replace($class, '\Configuration', strrpos($class, '\\'));
        $class = $container->get_reflection_class($class);
        if (!$class) {
            return null;
        }
        if (!$class->implements_interface(Configuration_Interface::class)) {
            throw new LogicException(\sprintf('The extension configuration class "%s" must implement "%s".', $class->get_name(), Configuration_Interface::class));
        }
        if (!($constructor = $class->get_constructor()) || !$constructor->get_number_of_required_parameters()) {
            return $class->new_instance();
        }
        return null;
    }
    final protected function process_configuration(Configuration_Interface $configuration, array $configs): array
    {
        $processor = new Processor();
        return $this->processed_configs[] = $processor->process_configuration($configuration, $configs);
    }
    /**
     * @internal
     */
    final public function get_processed_configs(): array
    {
        try {
            return $this->processed_configs;
        } finally {
            $this->processed_configs = [];
        }
    }
    /**
     * @throws InvalidArgumentException When the config is not enableable
     */
    protected function is_config_enabled(Container_Builder $container, array $config): bool
    {
        if (!\array_key_exists('enabled', $config)) {
            throw new InvalidArgumentException("The config array has no 'enabled' key.");
        }
        return (bool) $container->get_parameter_bag()->resolve_value($config['enabled']);
    }
}