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
namespace Symfony\Component\Cache\Dependency_Injection;

use Symfony\Component\Cache\Pruneable_Interface;
use Symfony\Component\Dependency_Injection\Argument\Iterator_Argument;
use Symfony\Component\Dependency_Injection\Compiler\Compiler_Pass_Interface;
use Symfony\Component\Dependency_Injection\Container_Builder;
use Symfony\Component\Dependency_Injection\Reference;
/**
 * @author Rob Frawley 2nd <rmf@src.run>
 */
class Cache_Pool_Pruner_Pass implements Compiler_Pass_Interface
{
    public function process(Container_Builder $container): void
    {
        if (!$container->has_definition('console.command.cache_pool_prune')) {
            return;
        }
        $services = [];
        foreach ($container->find_tagged_service_ids('cache.pool') as $id => $tags) {
            if ($tags[0]['pruneable'] ?? $container->get_reflection_class($container->get_definition($id)->get_class(), false)?->implements_interface(Pruneable_Interface::class) ?? false) {
                $services[$tags[0]['name'] ?? $id] = new Reference($id);
            }
        }
        $container->get_definition('console.command.cache_pool_prune')->replace_argument(0, new Iterator_Argument($services));
    }
}