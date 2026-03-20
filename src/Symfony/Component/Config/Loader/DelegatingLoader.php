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
/**
 * DelegatingLoader delegates loading to other loaders using a loader resolver.
 *
 * This loader acts as an array of LoaderInterface objects - each having
 * a chance to load a given resource (handled by the resolver)
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class Delegating_Loader extends Loader
{
    public function __construct(Loader_Resolver_Interface $resolver)
    {
        $this->resolver = $resolver;
    }
    public function load(mixed $resource, ?string $type = null): mixed
    {
        if (false === $loader = $this->resolver->resolve($resource, $type)) {
            throw new Loader_Load_Exception($resource, null, 0, null, $type);
        }
        return $loader->load($resource, $type);
    }
    public function supports(mixed $resource, ?string $type = null): bool
    {
        return false !== $this->resolver->resolve($resource, $type);
    }
}