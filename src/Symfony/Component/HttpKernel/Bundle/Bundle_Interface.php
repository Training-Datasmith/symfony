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
namespace Symfony\Component\Http_Kernel\Bundle;

use Symfony\Component\Dependency_Injection\Container_Builder;
use Symfony\Component\Dependency_Injection\Container_Interface;
use Symfony\Component\Dependency_Injection\Extension\Extension_Interface;
/**
 * BundleInterface.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
interface Bundle_Interface
{
    /**
     * Boots the Bundle.
     */
    public function boot(): void;
    /**
     * Shutdowns the Bundle.
     */
    public function shutdown(): void;
    /**
     * Builds the bundle.
     *
     * It is only ever called once when the cache is empty.
     */
    public function build(Container_Builder $container): void;
    /**
     * Returns the container extension that should be implicitly loaded.
     */
    public function get_container_extension(): ?Extension_Interface;
    /**
     * Returns the bundle name (the class short name).
     */
    public function get_name(): string;
    /**
     * Gets the Bundle namespace.
     */
    public function get_namespace(): string;
    /**
     * Gets the Bundle directory path.
     *
     * The path should always be returned as a Unix path (with /).
     */
    public function get_path(): string;
    public function set_container(?Container_Interface $container): void;
}