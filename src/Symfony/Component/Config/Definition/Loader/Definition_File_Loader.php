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
namespace Symfony\Component\Config\Definition\Loader;

use Symfony\Component\Config\Definition\Builder\Tree_Builder;
use Symfony\Component\Config\Definition\Configurator\Definition_Configurator;
use Symfony\Component\Config\File_Locator_Interface;
use Symfony\Component\Config\Loader\File_Loader;
use Symfony\Component\Dependency_Injection\Container_Builder;
/**
 * DefinitionFileLoader loads config definitions from a PHP file.
 *
 * The PHP file is required.
 *
 * @author Yonel Ceruto <yonelceruto@gmail.com>
 */
class Definition_File_Loader extends File_Loader
{
    public function __construct(private readonly Tree_Builder $tree_builder, File_Locator_Interface $locator, private readonly ?Container_Builder $container = null)
    {
        parent::__construct($locator);
    }
    public function load(mixed $resource, ?string $type = null): mixed
    {
        // the loader variable is exposed to the included file below
        $loader = $this;
        $path = $this->locator->locate($resource);
        $this->set_current_dir(\dirname($path));
        $this->container?->file_exists($path);
        // the closure forbids access to the private scope in the included file
        $load = \Closure::bind(static fn($file) => include $file, null, null);
        $callback = $load($path);
        if (\is_object($callback) && \is_callable($callback)) {
            $this->call_configurator($callback, new Definition_Configurator($this->tree_builder, $this, $path, $resource), $path);
        }
        return null;
    }
    public function supports(mixed $resource, ?string $type = null): bool
    {
        if (!\is_string($resource)) {
            return false;
        }
        if (null === $type && 'php' === pathinfo($resource, \PATHINFO_EXTENSION)) {
            return true;
        }
        return 'php' === $type;
    }
    private function call_configurator(callable $callback, Definition_Configurator $configurator, string $path): void
    {
        $callback = $callback(...);
        $arguments = [];
        $r = new \ReflectionFunction($callback);
        foreach ($r->get_parameters() as $parameter) {
            $reflection_type = $parameter->get_type();
            if (!$reflection_type instanceof \ReflectionNamedType) {
                throw new \InvalidArgumentException(\sprintf('Could not resolve argument "$%s" for "%s". You must typehint it (for example with "%s").', $parameter->get_name(), $path, Definition_Configurator::class));
            }
            $arguments[] = match ($reflection_type->get_name()) {
                Definition_Configurator::class => $configurator,
                Tree_Builder::class => $this->tree_builder,
                File_Loader::class, self::class => $this,
            };
        }
        $callback(...$arguments);
    }
}
/**
 * @internal
 */
final class Protected_Definition_File_Loader extends Definition_File_Loader
{
}