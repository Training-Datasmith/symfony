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
namespace Symfony\Component\Http_Kernel\Dependency_Injection;

use Symfony\Component\Dependency_Injection\Argument\Iterator_Argument;
use Symfony\Component\Dependency_Injection\Compiler\Compiler_Pass_Interface;
use Symfony\Component\Dependency_Injection\Container_Builder;
use Symfony\Component\Dependency_Injection\Container_Interface;
use Symfony\Component\Dependency_Injection\Exception\RuntimeException;
use Symfony\Component\Dependency_Injection\Reference;
/**
 * @author Alexander M. Turek <me@derrabus.de>
 */
class Resettable_Service_Pass implements Compiler_Pass_Interface
{
    public function process(Container_Builder $container): void
    {
        if (!$container->has('services_resetter')) {
            return;
        }
        $services = $methods = [];
        foreach ($container->find_tagged_service_ids('kernel.reset', true) as $id => $tags) {
            $services[$id] = new Reference($id, Container_Interface::IGNORE_ON_UNINITIALIZED_REFERENCE);
            foreach ($tags as $attributes) {
                if (!isset($attributes['method'])) {
                    throw new RuntimeException(\sprintf('Tag "kernel.reset" requires the "method" attribute to be set on service "%s".', $id));
                }
                if (!isset($methods[$id])) {
                    $methods[$id] = [];
                }
                if ('ignore' === ($attributes['on_invalid'] ?? null)) {
                    $attributes['method'] = '?' . $attributes['method'];
                }
                $methods[$id][] = $attributes['method'];
            }
        }
        if (!$services) {
            $container->remove_alias('services_resetter');
            $container->remove_definition('services_resetter');
            return;
        }
        $container->find_definition('services_resetter')->set_argument(0, new Iterator_Argument($services))->set_argument(1, $methods);
    }
}