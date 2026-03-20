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
namespace Symfony\Component\Dependency_Injection\Lazy_Proxy\Instantiator;

use Symfony\Component\Dependency_Injection\Container_Interface;
use Symfony\Component\Dependency_Injection\Definition;
use Symfony\Component\Dependency_Injection\Exception\InvalidArgumentException;
use Symfony\Component\Dependency_Injection\Lazy_Proxy\Php_Dumper\Lazy_Service_Dumper;
/**
 * @author Nicolas Grekas <p@tchwork.com>
 */
final class Lazy_Service_Instantiator implements Instantiator_Interface
{
    public function instantiate_proxy(Container_Interface $container, Definition $definition, string $id, callable $real_instantiator): object
    {
        $dumper = new Lazy_Service_Dumper();
        if (!$dumper->is_proxy_candidate($definition, $as_ghost_object, $id)) {
            throw new InvalidArgumentException(\sprintf('Cannot instantiate lazy proxy for service "%s".', $id));
        }
        if ($as_ghost_object) {
            return (new \ReflectionClass($definition->get_class()))->new_lazy_ghost(static function ($ghost) use ($real_instantiator): void {
                $real_instantiator($ghost);
            });
        }
        $class = null;
        if (!class_exists($proxy_class = $dumper->get_proxy_class($definition, false, $class), false)) {
            eval($dumper->get_proxy_code($definition, $id));
        }
        if ($definition->get_class() === $proxy_class) {
            return $class->new_lazy_proxy($real_instantiator);
        }
        return $proxy_class::create_lazy_proxy($real_instantiator);
    }
}