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

use Symfony\Component\Config\Definition\Base_Node;
use Symfony\Component\Config\Definition\Configuration_Interface;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\Dependency_Injection\Container_Builder;
use Symfony\Component\Dependency_Injection\Extension\Configuration_Extension_Interface;
use Symfony\Component\Dependency_Injection\Parameter_Bag\Env_Placeholder_Parameter_Bag;
use Symfony\Component\Dependency_Injection\Parameter_Bag\Parameter_Bag;
/**
 * Validates environment variable placeholders used in extension configuration with dummy values.
 *
 * @author Roland Franssen <franssen.roland@gmail.com>
 */
class Validate_Env_Placeholders_Pass implements Compiler_Pass_Interface
{
    private const TYPE_FIXTURES = ['array' => [], 'bool' => false, 'float' => 0.0, 'int' => 0, 'string' => ''];
    private array $extension_config = [];
    public function process(Container_Builder $container): void
    {
        $this->extension_config = [];
        if (!class_exists(Base_Node::class) || !$extensions = $container->get_extensions()) {
            return;
        }
        $resolving_bag = $container->get_parameter_bag();
        if (!$resolving_bag instanceof Env_Placeholder_Parameter_Bag) {
            return;
        }
        $default_bag = new Parameter_Bag($resolving_bag->all());
        $env_types = $resolving_bag->get_provided_types();
        foreach ($resolving_bag->get_env_placeholders() + $resolving_bag->get_unused_env_placeholders() as $env => $placeholders) {
            $values = $this->get_placeholder_values($env, $default_bag, $env_types);
            foreach ($placeholders as $placeholder) {
                Base_Node::set_placeholder($placeholder, $values);
            }
        }
        $processor = new Processor();
        foreach ($extensions as $name => $extension) {
            if (!($extension instanceof Configuration_Extension_Interface || $extension instanceof Configuration_Interface)) {
                // this extension has no semantic configuration or was not called
                continue;
            }
            if (!$config = array_filter($container->get_extension_config($name))) {
                // this extension has no semantic configuration or was not called
                continue;
            }
            $config = $resolving_bag->resolve_value($config);
            if ($extension instanceof Configuration_Interface) {
                $configuration = $extension;
            } elseif (null === $configuration = $extension->get_configuration($config, $container)) {
                continue;
            }
            $this->extension_config[$name] = $processor->process_configuration($configuration, $config);
        }
        $resolving_bag->clear_unused_env_placeholders();
    }
    /**
     * @internal
     */
    public function get_extension_config(): array
    {
        try {
            return $this->extension_config;
        } finally {
            $this->extension_config = [];
        }
    }
    /**
     * @param array<string, list<string>> $envTypes
     *
     * @return array<string, mixed>
     */
    private function get_placeholder_values(string $env, Parameter_Bag $default_bag, array $env_types): array
    {
        if (false === $i = strpos($env, ':')) {
            [$default, $default_type] = $this->get_parameter_default_and_default_type("env({$env})", $default_bag);
            return [$default_type => $default];
        }
        $prefix = substr($env, 0, $i);
        if ('default' === $prefix) {
            $parts = explode(':', $env);
            array_shift($parts);
            // Remove 'default' prefix
            $parameter = array_shift($parts);
            // Retrieve and remove parameter
            [$default_parameter, $default_parameter_type] = $this->get_parameter_default_and_default_type($parameter, $default_bag);
            return [$default_parameter_type => $default_parameter, ...$this->get_placeholder_values(implode(':', $parts), $default_bag, $env_types)];
        }
        $values = [];
        foreach ($env_types[$prefix] ?? ['string'] as $type) {
            $values[$type] = self::TYPE_FIXTURES[$type] ?? null;
        }
        return $values;
    }
    /**
     * @return array{0: string, 1: string}
     */
    private function get_parameter_default_and_default_type(string $name, Parameter_Bag $default_bag): array
    {
        $default = $default_bag->has($name) ? $default_bag->get($name) : self::TYPE_FIXTURES['string'];
        $default_type = null !== $default ? get_debug_type($default) : 'string';
        return [$default, $default_type];
    }
}