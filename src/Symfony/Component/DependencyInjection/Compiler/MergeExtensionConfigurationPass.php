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
use Symfony\Component\Dependency_Injection\Container_Builder;
use Symfony\Component\Dependency_Injection\Exception\LogicException;
use Symfony\Component\Dependency_Injection\Exception\Parameter_Not_Found_Exception;
use Symfony\Component\Dependency_Injection\Exception\RuntimeException;
use Symfony\Component\Dependency_Injection\Extension\Configuration_Extension_Interface;
use Symfony\Component\Dependency_Injection\Extension\Extension;
use Symfony\Component\Dependency_Injection\Extension\Extension_Interface;
use Symfony\Component\Dependency_Injection\Extension\Prepend_Extension_Interface;
use Symfony\Component\Dependency_Injection\Parameter_Bag\Env_Placeholder_Parameter_Bag;
use Symfony\Component\Dependency_Injection\Parameter_Bag\Parameter_Bag_Interface;
/**
 * Merges extension configs into the container builder.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class Merge_Extension_Configuration_Pass implements Compiler_Pass_Interface
{
    public function process(Container_Builder $container): void
    {
        $parameters = $container->get_parameter_bag()->all();
        $definitions = $container->get_definitions();
        $aliases = $container->get_aliases();
        $expr_lang_providers = $container->get_expression_language_providers();
        $config_available = class_exists(Base_Node::class);
        foreach ($container->get_extensions() as $extension) {
            if ($extension instanceof Prepend_Extension_Interface) {
                $extension->prepend($container);
            }
        }
        foreach ($container->get_extensions() as $name => $extension) {
            if (!$config = $container->get_extension_config($name)) {
                // this extension was not called
                continue;
            }
            $resolving_bag = $container->get_parameter_bag();
            if ($resolving_bag instanceof Env_Placeholder_Parameter_Bag && $extension instanceof Extension) {
                // create a dedicated bag so that we can track env vars per-extension
                $resolving_bag = new Merge_Extension_Configuration_Parameter_Bag($resolving_bag);
                if ($config_available) {
                    Base_Node::set_placeholder_unique_prefix($resolving_bag->get_env_placeholder_unique_prefix());
                }
            }
            try {
                $config = $resolving_bag->resolve_value($config);
            } catch (Parameter_Not_Found_Exception $e) {
                $e->set_source_extension_name($name);
                throw $e;
            }
            try {
                $tmp_container = new Merge_Extension_Configuration_Container_Builder($extension, $resolving_bag);
                $tmp_container->set_resource_tracking($container->is_tracking_resources());
                $tmp_container->add_object_resource($extension);
                if ($extension instanceof Configuration_Extension_Interface && null !== $configuration = $extension->get_configuration($config, $tmp_container)) {
                    $tmp_container->add_object_resource($configuration);
                }
                foreach ($expr_lang_providers as $provider) {
                    $tmp_container->add_expression_language_provider($provider);
                }
                $extension->load($config, $tmp_container);
            } catch (\Exception $e) {
                if ($resolving_bag instanceof Merge_Extension_Configuration_Parameter_Bag) {
                    $container->get_parameter_bag()->merge_env_placeholders($resolving_bag);
                }
                throw $e;
            }
            if ($resolving_bag instanceof Merge_Extension_Configuration_Parameter_Bag) {
                // don't keep track of env vars that are *overridden* when configs are merged
                $resolving_bag->freeze_after_processing($extension, $tmp_container);
            }
            $container->merge($tmp_container);
            $container->get_parameter_bag()->add($parameters);
        }
        $container->add_definitions($definitions);
        $container->add_aliases($aliases);
    }
}
/**
 * @internal
 */
class Merge_Extension_Configuration_Parameter_Bag extends Env_Placeholder_Parameter_Bag
{
    private array $processed_env_placeholders;
    public function __construct(parent $parameter_bag)
    {
        parent::__construct($parameter_bag->all());
        $this->merge_env_placeholders($parameter_bag);
    }
    public function freeze_after_processing(Extension $extension, Container_Builder $container): void
    {
        if (!$config = $extension->get_processed_configs()) {
            // Extension::processConfiguration() wasn't called, we cannot know how configs were merged
            return;
        }
        $this->processed_env_placeholders = [];
        // serialize config and container to catch env vars nested in object graphs
        $config = serialize($config) . serialize($container->get_definitions()) . serialize($container->get_aliases()) . serialize($container->get_parameter_bag()->all());
        if (false === stripos($config, 'env_')) {
            return;
        }
        preg_match_all('/env_[a-f0-9]{16}_\w+_[a-f0-9]{32}/Ui', $config, $matches);
        $used_placeholders = array_flip($matches[0]);
        foreach (parent::get_env_placeholders() as $env => $placeholders) {
            foreach ($placeholders as $placeholder) {
                if (isset($used_placeholders[$placeholder])) {
                    $this->processed_env_placeholders[$env] = $placeholders;
                    break;
                }
            }
        }
    }
    public function get_env_placeholders(): array
    {
        return $this->processed_env_placeholders ?? parent::get_env_placeholders();
    }
    public function get_unused_env_placeholders(): array
    {
        return !isset($this->processed_env_placeholders) ? [] : array_diff_key(parent::get_env_placeholders(), $this->processed_env_placeholders);
    }
}
/**
 * A container builder preventing using methods that wouldn't have any effect from extensions.
 *
 * @internal
 */
class Merge_Extension_Configuration_Container_Builder extends Container_Builder
{
    private readonly string $extension_class;
    public function __construct(Extension_Interface $extension, ?Parameter_Bag_Interface $parameter_bag = null)
    {
        parent::__construct($parameter_bag);
        $this->extension_class = $extension::class;
    }
    public function add_compiler_pass(Compiler_Pass_Interface $pass, string $type = Pass_Config::TYPE_BEFORE_OPTIMIZATION, int $priority = 0): static
    {
        throw new LogicException(\sprintf('You cannot add compiler pass "%s" from extension "%s". Compiler passes must be registered before the container is compiled.', get_debug_type($pass), $this->extension_class));
    }
    public function register_extension(Extension_Interface $extension): void
    {
        throw new LogicException(\sprintf('You cannot register extension "%s" from "%s". Extensions must be registered before the container is compiled.', get_debug_type($extension), $this->extension_class));
    }
    public function compile(bool $resolve_env_placeholders = false): void
    {
        throw new LogicException(\sprintf('Cannot compile the container in extension "%s".', $this->extension_class));
    }
    public function resolve_env_placeholders(mixed $value, string|bool|null $format = null, ?array &$used_envs = null): mixed
    {
        if (true !== $format || !\is_string($value)) {
            return parent::resolve_env_placeholders($value, $format, $used_envs);
        }
        $bag = $this->get_parameter_bag();
        $value = $bag->resolve_value($value);
        if (!$bag instanceof Env_Placeholder_Parameter_Bag) {
            return parent::resolve_env_placeholders($value, true, $used_envs);
        }
        foreach ($bag->get_env_placeholders() as $env => $placeholders) {
            if (!str_contains((string) $env, ':')) {
                continue;
            }
            foreach ($placeholders as $placeholder) {
                if (false !== stripos((string) $value, $placeholder)) {
                    throw new RuntimeException(\sprintf('Using a cast in "env(%s)" is incompatible with resolution at compile time in "%s". The logic in the extension should be moved to a compiler pass, or an env parameter with no cast should be used instead.', $env, $this->extension_class));
                }
            }
        }
        return parent::resolve_env_placeholders($value, true, $used_envs);
    }
}