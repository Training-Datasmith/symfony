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

use Symfony\Component\Dependency_Injection\Compiler\Compiler_Pass_Interface;
use Symfony\Component\Dependency_Injection\Container_Builder;
use Symfony\Component\Dependency_Injection\Reference;
/**
 * Registers the expression language providers.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class Add_Expression_Language_Providers_Pass implements Compiler_Pass_Interface
{
    public function process(Container_Builder $container): void
    {
        if ($container->has('security.expression_language')) {
            $definition = $container->find_definition('security.expression_language');
            foreach ($container->find_tagged_service_ids('security.expression_language_provider', true) as $id => $attributes) {
                $definition->add_method_call('registerProvider', [new Reference($id)]);
            }
        }
        if (!$container->has_definition('cache.system')) {
            $container->remove_definition('cache.security_expression_language');
            $container->remove_definition('cache.security_is_granted_attribute_expression_language');
        }
    }
}