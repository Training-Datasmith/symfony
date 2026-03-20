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
namespace Symfony\Component\Asset;

use Symfony\Component\Asset\Exception\InvalidArgumentException;
use Symfony\Component\Asset\Exception\LogicException;
/**
 * Helps manage asset URLs.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Kris Wallsmith <kris@symfony.com>
 */
class Packages
{
    private array $packages = [];
    /**
     * @param PackageInterface[] $packages Additional packages indexed by name
     */
    public function __construct(private ?Package_Interface $default_package = null, iterable $packages = [])
    {
        foreach ($packages as $name => $package) {
            $this->add_package($name, $package);
        }
    }
    public function set_default_package(Package_Interface $default_package): void
    {
        $this->default_package = $default_package;
    }
    public function add_package(string $name, Package_Interface $package): void
    {
        $this->packages[$name] = $package;
    }
    /**
     * Returns an asset package.
     *
     * @param string|null $name The name of the package or null for the default package
     *
     * @throws InvalidArgumentException If there is no package by that name
     * @throws LogicException           If no default package is defined
     */
    public function get_package(?string $name = null): Package_Interface
    {
        if (null === $name) {
            if (null === $this->default_package) {
                throw new LogicException('There is no default asset package, configure one first.');
            }
            return $this->default_package;
        }
        if (!isset($this->packages[$name])) {
            throw new InvalidArgumentException(\sprintf('There is no "%s" asset package.', $name));
        }
        return $this->packages[$name];
    }
    /**
     * Gets the version to add to public URL.
     *
     * @param string      $path        A public path
     * @param string|null $packageName A package name
     */
    public function get_version(string $path, ?string $package_name = null): string
    {
        return $this->get_package($package_name)->get_version($path);
    }
    /**
     * Returns the public path.
     *
     * Absolute paths (i.e. http://...) are returned unmodified.
     *
     * @param string      $path        A public path
     * @param string|null $packageName The name of the asset package to use
     *
     * @return string A public path which takes into account the base path and URL path
     */
    public function get_url(string $path, ?string $package_name = null): string
    {
        return $this->get_package($package_name)->get_url($path);
    }
}