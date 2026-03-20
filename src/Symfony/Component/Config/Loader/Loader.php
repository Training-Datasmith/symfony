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

use Symfony\Component\Config\Exception\Loader_Load_Exception;
use Symfony\Component\Config\Exception\LogicException;
/**
 * Loader is the abstract class used by all built-in loaders.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
abstract class Loader implements Loader_Interface
{
    protected ?Loader_Resolver_Interface $resolver = null;
    public function __construct(protected ?string $env = null)
    {
    }
    public function get_resolver(): Loader_Resolver_Interface
    {
        if (null === $this->resolver) {
            throw new LogicException('Cannot get a resolver if none was set.');
        }
        return $this->resolver;
    }
    public function set_resolver(Loader_Resolver_Interface $resolver): void
    {
        $this->resolver = $resolver;
    }
    /**
     * Imports a resource.
     */
    public function import(mixed $resource, ?string $type = null): mixed
    {
        return $this->resolve($resource, $type)->load($resource, $type);
    }
    /**
     * Finds a loader able to load an imported resource.
     *
     * @throws LoaderLoadException If no loader is found
     */
    public function resolve(mixed $resource, ?string $type = null): Loader_Interface
    {
        if ($this->supports($resource, $type)) {
            return $this;
        }
        $loader = null === $this->resolver ? false : $this->resolver->resolve($resource, $type);
        if (false === $loader) {
            throw new Loader_Load_Exception($resource, null, 0, null, $type);
        }
        return $loader;
    }
}