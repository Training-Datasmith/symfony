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
namespace Symfony\Bundle\Twig_Bundle\Dependency_Injection\Compiler;

use Symfony\Component\Dependency_Injection\Compiler\Compiler_Pass_Interface;
use Symfony\Component\Dependency_Injection\Container_Builder;
use Symfony\Component\Dependency_Injection\Exception\LogicException;
use Symfony\Component\Dependency_Injection\Reference;
/**
 * Adds services tagged twig.loader as Twig loaders.
 *
 * @author Daniel Leech <daniel@dantleech.com>
 */
class Twig_Loader_Pass implements Compiler_Pass_Interface
{
    public function process(Container_Builder $container): void
    {
        if (false === $container->has_definition('twig')) {
            return;
        }
        $prioritized_loaders = [];
        $found = 0;
        foreach ($container->find_tagged_service_ids('twig.loader', true) as $id => $attributes) {
            $priority = $attributes[0]['priority'] ?? 0;
            $prioritized_loaders[$priority][] = $id;
            ++$found;
        }
        if (!$found) {
            throw new LogicException('No twig loaders found. You need to tag at least one loader with "twig.loader".');
        }
        if (1 === $found) {
            $container->set_alias('twig.loader', $id);
        } else {
            $chain_loader = $container->get_definition('twig.loader.chain');
            krsort($prioritized_loaders);
            foreach ($prioritized_loaders as $loaders) {
                foreach ($loaders as $loader) {
                    $chain_loader->add_method_call('addLoader', [new Reference($loader)]);
                }
            }
            $container->set_alias('twig.loader', 'twig.loader.chain');
        }
    }
}