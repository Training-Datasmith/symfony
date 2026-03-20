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
namespace Symfony\Bundle\Framework_Bundle\Dependency_Injection\Compiler;

use Symfony\Component\Config\Definition\Array_Node;
use Symfony\Component\Config\Definition\Array_Shape_Generator;
use Symfony\Component\Config\Definition\Configuration_Interface;
use Symfony\Component\Config\Definition\Prototyped_Array_Node;
use Symfony\Component\Config\Loader\Param_Configurator;
use Symfony\Component\Config\Resource\File_Resource;
use Symfony\Component\Dependency_Injection\Compiler\Compiler_Pass_Interface;
use Symfony\Component\Dependency_Injection\Container_Builder;
use Symfony\Component\Dependency_Injection\Extension\Configuration_Extension_Interface;
use Symfony\Component\Dependency_Injection\Extension\Extension_Interface;
use Symfony\Component\Dependency_Injection\Loader\Configurator\App_Reference;
use Symfony\Component\Expression_Language\Expression;
use Symfony\Component\Http_Kernel\Bundle\Bundle_Interface;
use Symfony\Component\Routing\Loader\Configurator\Routes_Reference;
/**
 * @internal
 */
class Php_Config_Reference_Dump_Pass implements Compiler_Pass_Interface
{
    private const REFERENCE_TEMPLATE = <<<'PHP'
    <?php
    
    // This file is auto-generated and is for apps only. Bundles SHOULD NOT rely on its content.
    
    namespace Symfony\Component\DependencyInjection\Loader\Configurator;
    
    use Symfony\Component\Config\Loader\ParamConfigurator as Param;
    
    {APP_TYPES}
    final class App
    {
        {APP_PARAM}
        public static function config(array $config): array
        {
            /** @var ConfigType $config */
            $config = AppReference::config($config);
    
            return $config;
        }
    }
    
    namespace Symfony\Component\Routing\Loader\Configurator;
    
    {ROUTES_TYPES}
    final class Routes
    {
        {ROUTES_PARAM}
        public static function config(array $config): array
        {
            return $config;
        }
    }
    
    PHP;
    private const WHEN_ENV_APP_TEMPLATE = <<<'PHPDOC'
    
     *     "when@{ENV}"?: array{
     *         imports?: ImportsConfig,
     *         parameters?: ParametersConfig,
     *         services?: ServicesConfig,{SHAPE}
     *     },
    PHPDOC;
    private const ROUTES_TYPES_TEMPLATE = <<<'PHPDOC'
    
     * @psalm-type RoutesConfig = array{{SHAPE}
     *     ...<string, RouteConfig|ImportConfig|AliasConfig>
     * }
     */
    PHPDOC;
    private const WHEN_ENV_ROUTES_TEMPLATE = <<<'PHPDOC'
    
     *     "when@{ENV}"?: array<string, RouteConfig|ImportConfig|AliasConfig>,
    PHPDOC;
    public function __construct(private readonly string $reference_file, private readonly array $bundles_definition)
    {
    }
    public function process(Container_Builder $container): void
    {
        $known_envs = $container->has_parameter('.container.known_envs') ? $container->get_parameter('.container.known_envs') : [$container->get_parameter('kernel.environment')];
        $known_envs = array_unique($known_envs);
        sort($known_envs);
        $extensions_per_env = [];
        $app_types = '';
        $any_env_extensions = [];
        $registered_extensions = $container->get_extensions();
        foreach ($this->bundles_definition as $bundle => $envs) {
            if (!is_subclass_of($bundle, Bundle_Interface::class)) {
                continue;
            }
            if (!$extension = (new $bundle())->get_container_extension()) {
                continue;
            }
            $extension_alias = $extension->get_alias();
            if (isset($registered_extensions[$extension_alias])) {
                $extension = $registered_extensions[$extension_alias];
                unset($registered_extensions[$extension_alias]);
            }
            if (!$configuration = $this->get_configuration($extension, $container)) {
                continue;
            }
            $tree = $configuration->get_config_tree_builder()->build_tree();
            if ($tree instanceof Array_Node && !$tree instanceof Prototyped_Array_Node && !$tree->get_children()) {
                continue;
            }
            $any_env_extensions[$extension_alias] = $extension;
            $type = $this->camel_case($extension_alias) . 'Config';
            $app_types .= \sprintf("\n * @psalm-type %s = %s", $type, Array_Shape_Generator::generate($tree));
            foreach ($known_envs as $env) {
                if ($envs[$env] ?? $envs['all'] ?? false) {
                    $extensions_per_env[$env][] = $extension;
                } else {
                    unset($any_env_extensions[$extension_alias]);
                }
            }
        }
        foreach ($registered_extensions as $alias => $extension) {
            if (!$configuration = $this->get_configuration($extension, $container)) {
                continue;
            }
            $tree = $configuration->get_config_tree_builder()->build_tree();
            if ($tree instanceof Array_Node && !$tree instanceof Prototyped_Array_Node && !$tree->get_children()) {
                continue;
            }
            $any_env_extensions[$alias] = $extension;
            $type = $this->camel_case($alias) . 'Config';
            $app_types .= \sprintf("\n * @psalm-type %s = %s", $type, Array_Shape_Generator::generate($tree));
        }
        krsort($extensions_per_env);
        $r = new \ReflectionClass(App_Reference::class);
        if (false === $i = strpos($phpdoc = $r->get_doc_comment(), "\n * @psalm-type ConfigType = ")) {
            throw new \LogicException(\sprintf('Cannot insert config shape in "%s".', App_Reference::class));
        }
        $app_types = substr_replace($phpdoc, $app_types, $i, 0);
        if (false === $i = strrpos($phpdoc = $app_types, "\n *     ...<string, ExtensionType|array{")) {
            throw new \LogicException(\sprintf('Cannot insert config shape in "%s".', App_Reference::class));
        }
        $app_types = substr_replace($phpdoc, $this->get_shape_for_extensions($any_env_extensions, $container), $i, 0);
        $i += \strlen($app_types) - \strlen($phpdoc);
        foreach ($extensions_per_env as $env => $extensions) {
            $app_types = substr_replace($app_types, strtr(self::WHEN_ENV_APP_TEMPLATE, ['{ENV}' => $env, '{SHAPE}' => $this->get_shape_for_extensions($extensions, $container, '    ')]), $i, 0);
        }
        $app_param = $r->get_method('config')->get_doc_comment();
        $r = new \ReflectionClass(Routes_Reference::class);
        if (false === $i = strpos($phpdoc = $r->get_doc_comment(), "\n * @psalm-type RoutesConfig = ")) {
            throw new \LogicException(\sprintf('Cannot insert config shape in "%s".', Routes_Reference::class));
        }
        $routes_types = '';
        foreach ($known_envs as $env) {
            $routes_types .= strtr(self::WHEN_ENV_ROUTES_TEMPLATE, ['{ENV}' => $env]);
        }
        if ('' !== $routes_types) {
            $routes_types = strtr(self::ROUTES_TYPES_TEMPLATE, ['{SHAPE}' => $routes_types]);
            $routes_types = substr_replace($phpdoc, $routes_types, $i);
        }
        $app_types = str_replace('\\' . Param_Configurator::class, 'Param', $app_types);
        if (!class_exists(Expression::class)) {
            $app_types = str_replace('|ExpressionConfigurator', '', $app_types);
        }
        $config_reference = strtr(self::REFERENCE_TEMPLATE, ['{APP_TYPES}' => $app_types, '{APP_PARAM}' => $app_param, '{ROUTES_TYPES}' => $routes_types, '{ROUTES_PARAM}' => $r->get_method('config')->get_doc_comment()]);
        $dir = \dirname($this->reference_file);
        if (is_dir($dir) && is_writable($dir)) {
            if (!is_file($this->reference_file) || file_get_contents($this->reference_file) !== $config_reference) {
                file_put_contents($this->reference_file, $config_reference);
            }
            $container->add_resource(new File_Resource($this->reference_file));
        }
    }
    private function camel_case(string $input): string
    {
        $output = ucfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', $input))));
        return preg_replace('#\W#', '', $output);
    }
    private function get_configuration(Extension_Interface $extension, Container_Builder $container): ?Configuration_Interface
    {
        return match (true) {
            $extension instanceof Configuration_Interface => $extension,
            $extension instanceof Configuration_Extension_Interface => $extension->get_configuration([], $container),
            default => null,
        };
    }
    private function get_shape_for_extensions(array $extensions, Container_Builder $container, string $indent = ''): string
    {
        $shape = '';
        foreach ($extensions as $extension) {
            if ($this->get_configuration($extension, $container)) {
                $type = $this->camel_case($extension->get_alias()) . 'Config';
                $shape .= \sprintf("\n *     %s%s?: %s,", $indent, $extension->get_alias(), $type);
            }
        }
        return $shape;
    }
}