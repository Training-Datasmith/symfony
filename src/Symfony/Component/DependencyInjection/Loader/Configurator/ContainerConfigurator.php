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

use Symfony\Component\Config\Loader\Param_Configurator;
use Symfony\Component\Dependency_Injection\Argument\Abstract_Argument;
use Symfony\Component\Dependency_Injection\Argument\Iterator_Argument;
use Symfony\Component\Dependency_Injection\Argument\Service_Locator_Argument;
use Symfony\Component\Dependency_Injection\Argument\Tagged_Iterator_Argument;
use Symfony\Component\Dependency_Injection\Container_Builder;
use Symfony\Component\Dependency_Injection\Definition;
use Symfony\Component\Dependency_Injection\Exception\InvalidArgumentException;
use Symfony\Component\Dependency_Injection\Extension\Extension_Interface;
use Symfony\Component\Dependency_Injection\Loader\Php_File_Loader;
use Symfony\Component\Dependency_Injection\Loader\Undefined_Extension_Handler;
use Symfony\Component\Expression_Language\Expression;
/**
 * @author Nicolas Grekas <p@tchwork.com>
 */
class Container_Configurator extends Abstract_Configurator
{
    public const FACTORY = 'container';
    private array $instanceof;
    private int $anonymous_count = 0;
    public function __construct(private Container_Builder $container, private Php_File_Loader $loader, array &$instanceof, private string $path, private string $file, private ?string $env = null)
    {
        $this->instanceof =& $instanceof;
    }
    final public function extension(string $namespace, array $config, bool $prepend = false): void
    {
        if ($prepend) {
            $this->container->prepend_extension_config($namespace, static::process_value($config));
            return;
        }
        if (!$this->container->has_extension($namespace)) {
            $extensions = array_filter(array_map(static fn(Extension_Interface $ext): string => $ext->get_alias(), $this->container->get_extensions()));
            throw new InvalidArgumentException(Undefined_Extension_Handler::get_error_message($namespace, $this->file, $namespace, $extensions));
        }
        $this->container->load_from_extension($namespace, static::process_value($config));
    }
    final public function import(string $resource, ?string $type = null, bool|string $ignore_errors = false, string|array|null $exclude = null): void
    {
        $this->loader->set_current_dir(\dirname($this->path));
        $this->loader->import($resource, $type, $ignore_errors, $this->file, $exclude);
    }
    final public function parameters(): Parameters_Configurator
    {
        return new Parameters_Configurator($this->container);
    }
    final public function services(): Services_Configurator
    {
        return new Services_Configurator($this->container, $this->loader, $this->instanceof, $this->path, $this->anonymous_count);
    }
    /**
     * Get the current environment to be able to write conditional configuration.
     */
    final public function env(): ?string
    {
        return $this->env;
    }
    final public function with_path(string $path): static
    {
        $clone = clone $this;
        $clone->path = $clone->file = $path;
        $clone->loader->set_current_dir(\dirname($path));
        return $clone;
    }
}
/**
 * Creates a parameter.
 */
function param(string $name): Param_Configurator
{
    return new Param_Configurator($name);
}
/**
 * Creates a reference to a service.
 */
function service(string $service_id): Reference_Configurator
{
    return new Reference_Configurator($service_id);
}
/**
 * Creates an inline service.
 */
function inline_service(?string $class = null): Inline_Service_Configurator
{
    return new Inline_Service_Configurator(new Definition($class));
}
/**
 * Creates a service locator.
 *
 * @param array<ReferenceConfigurator|InlineServiceConfigurator> $values
 */
function service_locator(array $values): Service_Locator_Argument
{
    $values = Abstract_Configurator::process_value($values, true);
    return new Service_Locator_Argument($values);
}
/**
 * Creates a lazy iterator.
 *
 * @param ReferenceConfigurator[] $values
 */
function iterator(array $values): Iterator_Argument
{
    return new Iterator_Argument(Abstract_Configurator::process_value($values, true));
}
/**
 * Creates a lazy iterator by tag name.
 *
 * @param string          $tag            The name of the tag identifying the target services
 * @param string|null     $indexAttribute The name of the attribute that defines the key referencing each service in the tagged collection
 * @param string|string[] $exclude        Services to exclude from the iterator
 * @param bool            $excludeSelf    Whether to automatically exclude the referencing service from the iterator
 */
function tagged_iterator(string $tag, ?string $index_attribute = null, string|array|null $exclude = [], bool|string|null $exclude_self = true, ...$_): Tagged_Iterator_Argument
{
    if (\func_num_args() > 4 || !\is_bool($exclude_self) || null === $exclude || \is_string($exclude) && str_starts_with($exclude, 'get') && !\array_key_exists('defaultIndexMethod', $_)) {
        [, , $default_index_method, $default_priority_method, $exclude, $exclude_self] = \func_get_args() + [2 => null, null, [], true];
    } else {
        $default_index_method = \array_key_exists('defaultIndexMethod', $_) ? $_['defaultIndexMethod'] : false;
        $default_priority_method = \array_key_exists('defaultPriorityMethod', $_) ? $_['defaultPriorityMethod'] : false;
    }
    if (false !== $default_index_method || false !== $default_priority_method) {
        return new Tagged_Iterator_Argument($tag, $index_attribute, $default_index_method, false, $default_priority_method, (array) $exclude, $exclude_self);
    }
    return new Tagged_Iterator_Argument($tag, $index_attribute, false, (array) $exclude, $exclude_self);
}
/**
 * Creates a service locator by tag name.
 *
 * @param string          $tag            The name of the tag identifying the target services
 * @param string|null     $indexAttribute The name of the attribute that defines the key referencing each service in the tagged collection
 * @param string|string[] $exclude        Services to exclude from the iterator
 * @param bool            $excludeSelf    Whether to automatically exclude the referencing service from the iterator
 */
function tagged_locator(string $tag, ?string $index_attribute = null, string|array|null $exclude = [], bool|string|null $exclude_self = true, ...$_): Service_Locator_Argument
{
    if (\func_num_args() > 4 || !\is_bool($exclude_self) || null === $exclude || \is_string($exclude) && str_starts_with($exclude, 'get') && !\array_key_exists('defaultIndexMethod', $_)) {
        [, , $default_index_method, $default_priority_method, $exclude, $exclude_self] = \func_get_args() + [2 => null, null, [], true];
    } else {
        $default_index_method = \array_key_exists('defaultIndexMethod', $_) ? $_['defaultIndexMethod'] : false;
        $default_priority_method = \array_key_exists('defaultPriorityMethod', $_) ? $_['defaultPriorityMethod'] : false;
    }
    if (false !== $default_index_method || false !== $default_priority_method) {
        return new Service_Locator_Argument(new Tagged_Iterator_Argument($tag, $index_attribute, $default_index_method, true, $default_priority_method, (array) $exclude, $exclude_self));
    }
    return new Service_Locator_Argument(new Tagged_Iterator_Argument($tag, $index_attribute, true, (array) $exclude, $exclude_self));
}
/**
 * Creates an expression.
 */
function expr(string $expression): Expression_Configurator
{
    return new Expression_Configurator($expression);
}
/**
 * Creates an abstract argument.
 */
function abstract_arg(string $description): Abstract_Argument
{
    return new Abstract_Argument($description);
}
/**
 * Creates an environment variable reference.
 */
function env(string $name): Env_Configurator
{
    return new Env_Configurator($name);
}
/**
 * Creates a closure service reference.
 */
function service_closure(string $service_id): Closure_Reference_Configurator
{
    return new Closure_Reference_Configurator($service_id);
}
/**
 * Creates a closure.
 */
function closure(string|array|\Closure|Reference_Configurator|Expression $callable): Inline_Service_Configurator
{
    return (new Inline_Service_Configurator(new Definition('Closure')))->factory(['Closure', 'fromCallable'])->args([$callable]);
}