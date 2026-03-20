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
namespace Symfony\Bundle\Framework_Bundle\Dependency_Injection\Compiler;

use Symfony\Component\Dependency_Injection\Argument\Service_Closure_Argument;
use Symfony\Component\Dependency_Injection\Compiler\Compiler_Pass_Interface;
use Symfony\Component\Dependency_Injection\Container_Builder;
use Symfony\Component\Dependency_Injection\Reference;
/**
 * @author Nicolas Grekas <p@tchwork.com>
 */
class Test_Service_Container_Real_Ref_Pass implements Compiler_Pass_Interface
{
    public function process(Container_Builder $container): void
    {
        if (!$container->has_definition('test.private_services_locator')) {
            return;
        }
        $private_container = $container->get_definition('test.private_services_locator');
        $definitions = $container->get_definitions();
        $private_services = $private_container->get_argument(0);
        $renamed_ids = [];
        foreach ($private_services as $id => $argument) {
            if (isset($definitions[$target = (string) $argument->get_values()[0]])) {
                $argument->set_values([new Reference($target)]);
                if ($id !== $target) {
                    $renamed_ids[$id] = $target;
                }
                if ($inner = $definitions[$target]->get_tag('container.decorator')[0]['inner'] ?? null) {
                    $renamed_ids[$id] = $inner;
                }
            } else {
                unset($private_services[$id]);
            }
        }
        foreach ($container->get_aliases() as $id => $target) {
            while ($container->has_alias($target = (string) $target)) {
                $target = $container->get_alias($target);
            }
            if ($definitions[$target]->has_tag('container.private')) {
                $private_services[$id] = new Service_Closure_Argument(new Reference($target));
            }
            $renamed_ids[$id] = $target;
        }
        $private_container->replace_argument(0, $private_services);
        if ($container->has_definition('test.service_container') && $renamed_ids) {
            $container->get_definition('test.service_container')->set_argument(2, $renamed_ids);
        }
        $non_shared_services = [];
        foreach ($definitions as $id => $definition) {
            if (($id && '.' !== $id[0] || isset($private_services[$id])) && !$definition->is_shared() && !$definition->has_errors() && !$definition->is_abstract()) {
                $non_shared_services[$id] = true;
            }
        }
        if ($container->has_definition('test.service_container') && $non_shared_services) {
            $container->get_definition('test.service_container')->set_argument(3, $non_shared_services);
        }
    }
}