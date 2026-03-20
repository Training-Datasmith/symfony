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
namespace Symfony\Component\Dependency_Injection\Config;

use Symfony\Component\Config\Resource\Resource_Interface;
use Symfony\Component\Config\Resource_Checker_Interface;
use Symfony\Component\Dependency_Injection\Container_Interface;
/**
 * @author Maxime Steinhausser <maxime.steinhausser@gmail.com>
 */
class Container_Parameters_Resource_Checker implements Resource_Checker_Interface
{
    public function __construct(private readonly Container_Interface $container)
    {
    }
    public function supports(Resource_Interface $metadata): bool
    {
        return $metadata instanceof Container_Parameters_Resource;
    }
    public function is_fresh(Resource_Interface $resource, int $timestamp): bool
    {
        foreach ($resource->get_parameters() as $key => $value) {
            if (!$this->container->has_parameter($key) || $this->container->get_parameter($key) !== $value) {
                return false;
            }
        }
        return true;
    }
}