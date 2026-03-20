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

use Symfony\Component\Dependency_Injection\Compiler\Compiler_Pass_Interface;
use Symfony\Component\Dependency_Injection\Container_Builder;
use Symfony\Component\Dependency_Injection\Reference;
/**
 * @author Nicolas Grekas <p@tchwork.com>
 */
class Cache_Pool_Clearer_Pass implements Compiler_Pass_Interface
{
    public function process(Container_Builder $container): void
    {
        $container->get_parameter_bag()->remove('cache.prefix.seed');
        foreach ($container->find_tagged_service_ids('cache.pool.clearer') as $id => $attr) {
            $clearer = $container->get_definition($id);
            $pools = [];
            foreach ($clearer->get_argument(0) as $name => $ref) {
                if ($container->has_definition($ref)) {
                    $pools[$name] = new Reference($ref);
                }
            }
            $clearer->replace_argument(0, $pools);
        }
    }
}