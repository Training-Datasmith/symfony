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
namespace Symfony\Component\Config\Loader;

/**
 * LoaderResolver selects a loader for a given resource.
 *
 * A resource can be anything (e.g. a full path to a config file or a Closure).
 * Each loader determines whether it can load a resource and how.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class Loader_Resolver implements Loader_Resolver_Interface
{
    /**
     * @var LoaderInterface[] An array of LoaderInterface objects
     */
    private array $loaders = [];
    /**
     * @param LoaderInterface[] $loaders An array of loaders
     */
    public function __construct(array $loaders = [])
    {
        foreach ($loaders as $loader) {
            $this->add_loader($loader);
        }
    }
    public function resolve(mixed $resource, ?string $type = null): Loader_Interface|false
    {
        foreach ($this->loaders as $loader) {
            if ($loader->supports($resource, $type)) {
                return $loader;
            }
        }
        return false;
    }
    public function add_loader(Loader_Interface $loader): void
    {
        $this->loaders[] = $loader;
        $loader->set_resolver($this);
    }
    /**
     * Returns the registered loaders.
     *
     * @return LoaderInterface[]
     */
    public function get_loaders(): array
    {
        return $this->loaders;
    }
}