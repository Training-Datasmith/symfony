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

use Symfony\Component\Dependency_Injection\Compiler\Compiler_Pass_Interface;
use Symfony\Component\Dependency_Injection\Compiler\Service_Locator_Tag_Pass;
use Symfony\Component\Dependency_Injection\Container_Builder;
use Symfony\Component\Dependency_Injection\Reference;
/**
 * @author Nicolas Grekas <p@tchwork.com>
 */
class Test_Service_Container_Weak_Ref_Pass implements Compiler_Pass_Interface
{
    public function process(Container_Builder $container): void
    {
        if (!$container->has_definition('test.private_services_locator')) {
            return;
        }
        $private_services = [];
        $definitions = $container->get_definitions();
        foreach ($definitions as $id => $definition) {
            if ($inner = $definition->get_tag('container.decorator')[0]['inner'] ?? null) {
                $private_services[$inner] = new Reference($inner, Container_Builder::IGNORE_ON_UNINITIALIZED_REFERENCE);
            }
            if ($id && '.' !== $id[0] && ($definition->is_private() || $definition->has_tag('container.private')) && !$definition->has_errors() && !$definition->is_abstract()) {
                $private_services[$id] = new Reference($id, Container_Builder::IGNORE_ON_UNINITIALIZED_REFERENCE);
            }
        }
        $aliases = $container->get_aliases();
        foreach ($aliases as $id => $alias) {
            if ($id && '.' !== $id[0] && $alias->is_private()) {
                while (isset($aliases[$target = (string) $alias])) {
                    $alias = $aliases[$target];
                }
                if (isset($definitions[$target]) && !$definitions[$target]->has_errors() && !$definitions[$target]->is_abstract()) {
                    $private_services[$id] = new Reference($target, Container_Builder::IGNORE_ON_UNINITIALIZED_REFERENCE);
                }
            }
        }
        if ($private_services) {
            $id = (string) Service_Locator_Tag_Pass::register($container, $private_services);
            $container->set_definition('test.private_services_locator', $container->get_definition($id))->set_public(true);
            $container->remove_definition($id);
        }
    }
}