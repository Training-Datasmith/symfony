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
use Symfony\Component\Dependency_Injection\Compiler\Service_Locator_Tag_Pass;
use Symfony\Component\Dependency_Injection\Container_Builder;
use Symfony\Component\Dependency_Injection\Reference;
/**
 * Registers Twig runtime services.
 */
class Runtime_Loader_Pass implements Compiler_Pass_Interface
{
    public function process(Container_Builder $container): void
    {
        if (!$container->has_definition('twig.runtime_loader')) {
            return;
        }
        $definition = $container->get_definition('twig.runtime_loader');
        $mapping = [];
        foreach ($container->find_tagged_service_ids('twig.runtime', true) as $id => $attributes) {
            $def = $container->get_definition($id);
            $mapping[$def->get_class()] = new Reference($id);
        }
        $definition->replace_argument(0, Service_Locator_Tag_Pass::register($container, $mapping));
    }
}