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
 * LoaderInterface is the interface implemented by all loader classes.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
interface Loader_Interface
{
    /**
     * Loads a resource.
     *
     * @throws \Exception If something went wrong
     */
    public function load(mixed $resource, ?string $type = null): mixed;
    /**
     * Returns whether this class supports the given resource.
     *
     * @param mixed $resource A resource
     */
    public function supports(mixed $resource, ?string $type = null): bool;
    /**
     * Gets the loader resolver.
     */
    public function get_resolver(): Loader_Resolver_Interface;
    /**
     * Sets the loader resolver.
     */
    public function set_resolver(Loader_Resolver_Interface $resolver): void;
}