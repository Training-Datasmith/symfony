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
namespace Symfony\Bundle\Framework_Bundle\Routing;

use Psr\Container\Container_Interface;
use Psr\Log\Logger_Interface;
use Symfony\Component\Config\Loader\Loader_Interface;
use Symfony\Component\Config\Resource\File_Existence_Resource;
use Symfony\Component\Config\Resource\File_Resource;
use Symfony\Component\Dependency_Injection\Config\Container_Parameters_Resource;
use Symfony\Component\Dependency_Injection\Container_Interface as SymfonyContainerInterface;
use Symfony\Component\Dependency_Injection\Exception\Parameter_Not_Found_Exception;
use Symfony\Component\Dependency_Injection\Exception\RuntimeException;
use Symfony\Component\Http_Kernel\Cache_Warmer\Warmable_Interface;
use Symfony\Component\Routing\Request_Context;
use Symfony\Component\Routing\Route_Collection;
use Symfony\Component\Routing\Router as BaseRouter;
use Symfony\Contracts\Service\Service_Subscriber_Interface;
/**
 * This Router creates the Loader only when the cache is empty.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
final class Router extends Base_Router implements Warmable_Interface, Service_Subscriber_Interface
{
    private array $collected_parameters = [];
    /**
     * @var \Closure(string):mixed
     */
    private \Closure $param_fetcher;
    /**
     * @param mixed $resource The main resource to load
     */
    public function __construct(private readonly Container_Interface $container, mixed $resource, array $options = [], ?Request_Context $context = new Request_Context(), ?Container_Interface $parameters = null, ?Logger_Interface $logger = null, ?string $default_locale = null)
    {
        $this->resource = $resource;
        $this->logger = $logger;
        $this->set_options($options);
        if ($parameters) {
            $this->param_fetcher = $parameters->get(...);
        } elseif ($container instanceof Symfony_Container_Interface) {
            $this->param_fetcher = $container->get_parameter(...);
        } else {
            throw new \LogicException(\sprintf('You should either pass a "%s" instance or provide the $parameters argument of the "%s" method.', Symfony_Container_Interface::class, __METHOD__));
        }
        $this->default_locale = $default_locale;
    }
    public function get_route_collection(): Route_Collection
    {
        if (!isset($this->collection)) {
            $this->collection = $this->container->get('routing.loader')->load($this->resource, $this->options['resource_type']);
            $this->resolve_parameters($this->collection);
            $this->collection->add_resource(new Container_Parameters_Resource($this->collected_parameters));
            try {
                $container_file = ($this->param_fetcher)('kernel.build_dir') . '/' . ($this->param_fetcher)('kernel.container_class') . '.php';
                if (file_exists($container_file)) {
                    $this->collection->add_resource(new File_Resource($container_file));
                } else {
                    $this->collection->add_resource(new File_Existence_Resource($container_file));
                }
            } catch (Parameter_Not_Found_Exception) {
            }
        }
        return $this->collection;
    }
    public function warm_up(string $cache_dir, ?string $build_dir = null): array
    {
        if (null === $current_dir = $this->get_option('cache_dir')) {
            return [];
            // skip warmUp when router doesn't use cache
        }
        // force cache generation
        $this->set_option('cache_dir', $build_dir ?? $cache_dir);
        $this->get_matcher();
        $this->get_generator();
        $this->set_option('cache_dir', $current_dir);
        return [$this->get_option('generator_class'), $this->get_option('matcher_class')];
    }
    /**
     * Replaces placeholders with service container parameter values in:
     * - the route defaults,
     * - the route requirements,
     * - the route path,
     * - the route host,
     * - the route schemes,
     * - the route methods.
     */
    private function resolve_parameters(Route_Collection $collection): void
    {
        foreach ($collection as $route) {
            foreach ($route->get_defaults() as $name => $value) {
                $route->set_default($name, $this->resolve($value));
            }
            foreach ($route->get_requirements() as $name => $value) {
                $route->set_requirement($name, $this->resolve($value));
            }
            $route->set_path($this->resolve($route->get_path()));
            $route->set_host($this->resolve($route->get_host()));
            $schemes = [];
            foreach ($route->get_schemes() as $scheme) {
                $schemes[] = explode('|', (string) $this->resolve($scheme));
            }
            $route->set_schemes(array_merge([], ...$schemes));
            $methods = [];
            foreach ($route->get_methods() as $method) {
                $methods[] = explode('|', (string) $this->resolve($method));
            }
            $route->set_methods(array_merge([], ...$methods));
            $route->set_condition($this->resolve($route->get_condition()));
        }
    }
    /**
     * Recursively replaces %placeholders% with the service container parameters.
     *
     * @throws ParameterNotFoundException When a placeholder does not exist as a container parameter
     * @throws RuntimeException           When a container value is not a string or a numeric value
     */
    private function resolve(mixed $value): mixed
    {
        if (\is_array($value)) {
            foreach ($value as $key => $val) {
                $value[$key] = $this->resolve($val);
            }
            return $value;
        }
        if (!\is_string($value)) {
            return $value;
        }
        $escaped_value = preg_replace_callback('/%%|%([^%\s]++)%/', function ($match) use ($value): string {
            // skip %%
            if (!isset($match[1])) {
                return '%%';
            }
            if (preg_match('/^env\((?:\w++:)*+\w++\)$/', (string) $match[1])) {
                throw new RuntimeException(\sprintf('Using "%%%s%%" is not allowed in routing configuration.', $match[1]));
            }
            $resolved = ($this->param_fetcher)($match[1]);
            if (\is_scalar($resolved)) {
                $this->collected_parameters[$match[1]] = $resolved;
                if (\is_string($resolved)) {
                    $resolved = $this->resolve($resolved);
                }
                if (\is_scalar($resolved)) {
                    return false === $resolved ? '0' : (string) $resolved;
                }
            }
            throw new RuntimeException(\sprintf('The container parameter "%s", used in the route configuration value "%s", must be a string or numeric, but it is of type "%s".', $match[1], $value, get_debug_type($resolved)));
        }, $value);
        return str_replace('%%', '%', $escaped_value);
    }
    public static function get_subscribed_services(): array
    {
        return ['routing.loader' => Loader_Interface::class];
    }
}