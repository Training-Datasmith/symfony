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

use Monolog\Processor\Processor_Interface;
use Symfony\Component\Dependency_Injection\Argument\Bound_Argument;
use Symfony\Component\Dependency_Injection\Compiler\Compiler_Pass_Interface;
use Symfony\Component\Dependency_Injection\Container_Builder;
use Symfony\Component\Dependency_Injection\Reference;
use Symfony\Component\Security\Core\Authentication\Token\Storage\Token_Storage_Interface;
/**
 * Injects the session tracker enabler in "security.context_listener" + binds "security.untracked_token_storage" to ProcessorInterface instances.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 *
 * @internal
 */
class Register_Token_Usage_Tracking_Pass implements Compiler_Pass_Interface
{
    public function process(Container_Builder $container): void
    {
        if (!$container->has('security.untracked_token_storage')) {
            return;
        }
        $processor_autoconfiguration = $container->register_for_autoconfiguration(Processor_Interface::class);
        $processor_autoconfiguration->set_bindings($processor_autoconfiguration->get_bindings() + [Token_Storage_Interface::class => new Bound_Argument(new Reference('security.untracked_token_storage'), false)]);
        if (!$container->has('session.factory')) {
            $container->set_alias('security.token_storage', 'security.untracked_token_storage')->set_public(true);
            $container->get_definition('security.untracked_token_storage')->add_tag('kernel.reset', ['method' => 'reset']);
        } elseif ($container->has_definition('security.context_listener')) {
            $token_storage_class = $container->get_parameter_bag()->resolve_value($container->find_definition('security.token_storage')->get_class());
            if (method_exists($token_storage_class, 'enableUsageTracking')) {
                $container->get_definition('security.context_listener')->set_argument(6, [new Reference('security.token_storage'), 'enableUsageTracking']);
            }
        }
    }
}