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
namespace Symfony\Component\Dependency_Injection\Extension;

use Symfony\Component\Dependency_Injection\Container_Builder;
/**
 * ExtensionInterface is the interface implemented by container extension classes.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
interface Extension_Interface
{
    /**
     * Loads a specific configuration.
     *
     * @param array<array<mixed>> $configs
     *
     * @throws \InvalidArgumentException When provided tag is not defined in this extension
     */
    public function load(array $configs, Container_Builder $container): void;
    /**
     * Returns the recommended alias to use in XML.
     *
     * This alias is also the mandatory prefix to use when using YAML.
     */
    public function get_alias(): string;
}