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
namespace Symfony\Bundle\Security_Bundle\Dependency_Injection\Compiler;

use Symfony\Component\Dependency_Injection\Argument\Service_Closure_Argument;
use Symfony\Component\Dependency_Injection\Compiler\Compiler_Pass_Interface;
use Symfony\Component\Dependency_Injection\Container_Builder;
use Symfony\Component\Dependency_Injection\Definition;
use Symfony\Component\Dependency_Injection\Reference;
use Symfony\Component\Dependency_Injection\Service_Locator;
/**
 * @author Wouter de Jong <wouter@wouterj.nl>
 *
 * @internal
 */
class Register_Ldap_Locator_Pass implements Compiler_Pass_Interface
{
    public function process(Container_Builder $container): void
    {
        $definition = $container->set_definition('security.ldap_locator', new Definition(Service_Locator::class));
        $locators = [];
        foreach ($container->find_tagged_service_ids('ldap') as $service_id => $tags) {
            $locators[$service_id] = new Service_Closure_Argument(new Reference($service_id));
        }
        $definition->add_argument($locators);
    }
}