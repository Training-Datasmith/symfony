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
namespace Symfony\Bundle\Framework_Bundle\Kernel;

use Symfony\Bundle\Framework_Bundle\Framework_Bundle;
use Symfony\Component\Config\Loader\Loader_Interface;
use Symfony\Component\Dependency_Injection\Container_Builder;
use Symfony\Component\Dependency_Injection\Loader\Configurator\Abstract_Configurator;
use Symfony\Component\Dependency_Injection\Loader\Configurator\Container_Configurator;
use Symfony\Component\Dependency_Injection\Loader\Php_File_Loader as ContainerPhpFileLoader;
use Symfony\Component\Dependency_Injection\Reference;
use Symfony\Component\Routing\Loader\Configurator\Routing_Configurator;
use Symfony\Component\Routing\Loader\Php_File_Loader as RoutingPhpFileLoader;
use Symfony\Component\Routing\Route_Collection;
/**
 * A Kernel that provides configuration hooks.
 *
 * @author Ryan Weaver <ryan@knpuniversity.com>
 * @author Fabien Potencier <fabien@symfony.com>
 */
trait Micro_Kernel_Trait
{
    /**
     * @return list<string> The list of allowed environments - typically prod, dev, test - empty allows any environments
     */
    private function get_allowed_envs(): array
    {
        return [];
    }
    /**
     * Configures the container.
     *
     * You can register extensions:
     *
     *     $container->extension('framework', [
     *         'secret' => '%secret%'
     *     ]);
     *
     * Or services:
     *
     *     $container->services()->set('halloween', 'FooBundle\HalloweenProvider');
     *
     * Or parameters:
     *
     *     $container->parameters()->set('halloween', 'lot of fun');
     */
    private function configure_container(Container_Configurator $container, Loader_Interface $loader, Container_Builder $builder): void
    {
        $config_dir = preg_replace('{/config$}', '/{config}', $this->get_config_dir());
        $container->import($config_dir . '/{packages}/*.{php,yaml}');
        $container->import($config_dir . '/{packages}/' . $this->environment . '/*.{php,yaml}');
        if (is_file($this->get_config_dir() . '/services.yaml')) {
            $container->import($config_dir . '/services.yaml');
            $container->import($config_dir . '/{services}_' . $this->environment . '.yaml');
        } else {
            $container->import($config_dir . '/{services}.php');
            $container->import($config_dir . '/{services}_' . $this->environment . '.php');
        }
    }
    /**
     * Adds or imports routes into your application.
     *
     *     $routes->import($this->getConfigDir().'/*.{yaml,php}');
     *     $routes
     *         ->add('admin_dashboard', '/admin')
     *         ->controller('App\Controller\AdminController::dashboard')
     *     ;
     */
    private function configure_routes(Routing_Configurator $routes): void
    {
        $config_dir = preg_replace('{/config$}', '/{config}', $this->get_config_dir());
        $routes->import($config_dir . '/{routes}/' . $this->environment . '/*.{php,yaml}');
        $routes->import($config_dir . '/{routes}/*.{php,yaml}');
        if (is_file($this->get_config_dir() . '/routes.yaml')) {
            $routes->import($config_dir . '/routes.yaml');
        } else {
            $routes->import($config_dir . '/{routes}.php');
        }
        if ($file_name = (new \Reflection_Object($this))->get_file_name()) {
            $routes->import($file_name, 'attribute');
        }
    }
    /**
     * Gets the path to the configuration directory.
     */
    private function get_config_dir(): string
    {
        return $this->get_project_dir() . '/config';
    }
    /**
     * Gets the path to the bundles configuration file.
     */
    private function get_bundles_path(): string
    {
        return $this->get_config_dir() . '/bundles.php';
    }
    public function get_cache_dir(): string
    {
        if (null !== $dir = $_SERVER['APP_CACHE_DIR'] ?? null) {
            return $this->get_env_dir($dir);
        }
        return parent::get_cache_dir();
    }
    public function get_build_dir(): string
    {
        if (null !== $dir = $_SERVER['APP_BUILD_DIR'] ?? null) {
            return $this->get_env_dir($dir);
        }
        return parent::get_build_dir();
    }
    public function get_share_dir(): ?string
    {
        if (null !== $dir = $_SERVER['APP_SHARE_DIR'] ?? null) {
            if (false === $dir = filter_var($dir, \FILTER_VALIDATE_BOOL, \FILTER_NULL_ON_FAILURE) ?? $dir) {
                return null;
            }
            if (\is_string($dir)) {
                return $this->get_env_dir($dir);
            }
        }
        return parent::get_share_dir();
    }
    public function get_log_dir(): string
    {
        return $_SERVER['APP_LOG_DIR'] ?? parent::get_log_dir();
    }
    public function register_bundles(): iterable
    {
        if (!is_file($bundles_path = $this->get_bundles_path())) {
            yield new Framework_Bundle();
            return;
        }
        $contents = require $bundles_path;
        foreach ($contents as $class => $envs) {
            if ($envs[$this->environment] ?? $envs['all'] ?? false) {
                yield new $class();
            }
        }
    }
    public function register_container_configuration(Loader_Interface $loader): void
    {
        $loader->load(function (Container_Builder $container) use ($loader): void {
            $container->load_from_extension('framework', ['router' => ['resource' => 'kernel::loadRoutes', 'type' => 'service']]);
            $kernel_class = str_contains(static::class, "@anonymous\x00") ? parent::class : static::class;
            if (!$container->has_definition('kernel')) {
                $container->register('kernel', $kernel_class)->add_tag('controller.service_arguments')->set_autoconfigured(true)->set_synthetic(true)->set_public(true);
            }
            $kernel_definition = $container->get_definition('kernel');
            $kernel_definition->add_tag('routing.route_loader');
            $container->add_object_resource($this);
            $container->file_exists($this->get_bundles_path());
            $configure_container = new \ReflectionMethod($this, 'configureContainer');
            $configurator_class = $configure_container->get_number_of_parameters() > 0 && ($type = $configure_container->get_parameters()[0]->get_type()) instanceof \ReflectionNamedType && !$type->is_builtin() ? $type->get_name() : null;
            if ($configurator_class && !is_a(Container_Configurator::class, $configurator_class, true)) {
                $configure_container->get_closure($this)($container, $loader);
                return;
            }
            $file = (new \Reflection_Object($this))->get_file_name();
            /** @var ContainerPhpFileLoader $kernelLoader */
            $kernel_loader = $loader->get_resolver()->resolve($file);
            $kernel_loader->set_current_dir(\dirname($file));
            $instanceof =& \Closure::bind(fn&() => $this->instanceof, $kernel_loader, $kernel_loader)();
            $value_pre_processor = Abstract_Configurator::$value_pre_processor;
            Abstract_Configurator::$value_pre_processor = fn($value) => $this === $value ? new Reference('kernel') : $value;
            try {
                $configure_container->get_closure($this)(new Container_Configurator($container, $kernel_loader, $instanceof, $file, $file, $this->get_environment()), $loader, $container);
            } finally {
                $instanceof = [];
                $kernel_loader->register_aliases_for_singly_implemented_interfaces();
                Abstract_Configurator::$value_pre_processor = $value_pre_processor;
            }
            $container->set_alias($kernel_class, 'kernel')->set_public(true);
        });
    }
    /**
     * @internal
     */
    public function load_routes(Loader_Interface $loader): Route_Collection
    {
        $file = (new \Reflection_Object($this))->get_file_name();
        /** @var RoutingPhpFileLoader $kernelLoader */
        $kernel_loader = $loader->get_resolver()->resolve($file, 'php');
        $kernel_loader->set_current_dir(\dirname($file));
        $collection = new Route_Collection();
        $configure_routes = new \ReflectionMethod($this, 'configureRoutes');
        $configure_routes->get_closure($this)(new Routing_Configurator($collection, $kernel_loader, $file, $file, $this->get_environment()));
        foreach ($collection as $route) {
            $controller = $route->get_default('_controller');
            if (\is_array($controller) && [0, 1] === array_keys($controller) && $this === $controller[0]) {
                $route->set_default('_controller', ['kernel', $controller[1]]);
            } elseif ($controller instanceof \Closure && $this === ($r = new \ReflectionFunction($controller))->get_closure_this() && !$r->is_anonymous()) {
                $route->set_default('_controller', ['kernel', $r->name]);
            } elseif ($this::class === $controller && method_exists($this, '__invoke')) {
                $route->set_default('_controller', 'kernel');
            }
        }
        return $collection;
    }
    /**
     * Returns the kernel parameters.
     *
     * @return array<string, array|bool|string|int|float|\UnitEnum|null>
     */
    protected function get_kernel_parameters(): array
    {
        $parameters = parent::get_kernel_parameters();
        $bundles_path = $this->get_bundles_path();
        $bundles_definition = !is_file($bundles_path) ? [Framework_Bundle::class => ['all' => true]] : require $bundles_path;
        if (!$known_envs = array_flip($this->get_allowed_envs())) {
            $known_envs = [$this->environment => true];
            foreach ($bundles_definition as $envs) {
                $known_envs += $envs;
            }
            unset($known_envs['all']);
        } elseif (!isset($known_envs[$this->environment])) {
            throw new \InvalidArgumentException(\sprintf('The environment "%s" is not registered as allowed by "%s::getAllowedEnvs()".', $this->environment, static::class));
        }
        $parameters['.container.known_envs'] = array_keys($known_envs);
        $parameters['.kernel.config_dir'] = $this->get_config_dir();
        $parameters['.kernel.bundles_definition'] = $bundles_definition;
        return $parameters;
    }
    private function get_env_dir(string $dir): string
    {
        if ('' !== $dir && \in_array($dir[0], ['/', '\\'], true)) {
            return $dir . '/' . $this->environment;
        }
        if ('\\' === \DIRECTORY_SEPARATOR && ':' === ($dir[1] ?? '') && 65 <= \ord($dir[0]) && \ord($dir[0]) <= 122 && !\in_array($dir[0], ['[', ']', '^', '_', '`'], true)) {
            return $dir . '/' . $this->environment;
        }
        return $this->get_project_dir() . '/' . $dir . '/' . $this->environment;
    }
}