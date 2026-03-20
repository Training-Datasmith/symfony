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
namespace Symfony\Component\Dependency_Injection\Parameter_Bag;

use Symfony\Component\Dependency_Injection\Container;
/**
 * @author Nicolas Grekas <p@tchwork.com>
 */
class Container_Bag extends Frozen_Parameter_Bag implements Container_Bag_Interface
{
    public function __construct(private readonly Container $container)
    {
    }
    public function all(): array
    {
        return $this->container->get_parameter_bag()->all();
    }
    public function get(string $name): array|bool|string|int|float|\Unit_Enum|null
    {
        return $this->container->get_parameter($name);
    }
    public function has(string $name): bool
    {
        return $this->container->has_parameter($name);
    }
}