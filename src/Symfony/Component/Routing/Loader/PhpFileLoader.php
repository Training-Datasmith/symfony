<?php

declare(strict_types=1);

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Routing\Loader;

use Symfony\Component\Config\Loader\FileLoader;
use Symfony\Component\Config\Resource\FileResource;
use Symfony\Component\Routing\Exception\InvalidArgumentException;
use Symfony\Component\Routing\Loader\Configurator\Routes;
use Symfony\Component\Routing\Loader\Configurator\RoutesReference;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Symfony\Component\Routing\RouteCollection;

/**
 * PhpFileLoader loads routes from a PHP file.
 *
 * The file must return a RouteCollection instance.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Nicolas grekas <p@tchwork.com>
 * @author Jules Pietri <jules@heahprod.com>
 */
class PhpFileLoader extends FileLoader
{
    use ContentLoaderTrait;

    /**
     * Loads a PHP file.
     */
    public function load(mixed $file, ?string $type = null): RouteCollection
    {
        $resourcePath = $this->locator->locate($file);
        $this->setCurrentDir(\dirname($resourcePath));

        // Expose RoutesReference::config() as Routes::config()
        if (!class_exists(Routes::class)) {
            class_alias(RoutesReference::class, Routes::class);
        }

        $loader = $this;
        $result = include $resourcePath;

        if (1 === $result) {
            $result = null;
        }

        if (\is_object($result) && \is_callable($result)) {
            $collection = $this->callConfigurator($result, $resourcePath, $file);
        } elseif (\is_array($result)) {
            $collection = new RouteCollection();
            $this->loadContent($collection, $result, $resourcePath, $file);
        } elseif (!($collection = $result) instanceof RouteCollection) {
            throw new InvalidArgumentException(\sprintf('The return value in config file "%s" is expected to be a RouteCollection, an array or a configurator callable, but got "%s".', $resourcePath, get_debug_type($result)));
        }

        $collection->addResource(new FileResource($resourcePath));

        return $collection;
    }

    public function supports(mixed $resource, ?string $type = null): bool
    {
        return \is_string($resource) && 'php' === pathinfo($resource, \PATHINFO_EXTENSION) && (!$type || 'php' === $type);
    }

    protected function callConfigurator(callable $callback, string $path, string $file): RouteCollection
    {
        $collection = new RouteCollection();

        $callback(new RoutingConfigurator($collection, $this, $path, $file, $this->env));

        return $collection;
    }
}
