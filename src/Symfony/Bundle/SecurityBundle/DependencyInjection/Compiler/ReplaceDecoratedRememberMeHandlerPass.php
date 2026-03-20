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

use Symfony\Bundle\Security_Bundle\Remember_Me\Decorated_Remember_Me_Handler;
use Symfony\Component\Dependency_Injection\Compiler\Compiler_Pass_Interface;
use Symfony\Component\Dependency_Injection\Container_Builder;
/**
 * Replaces the DecoratedRememberMeHandler services with the real definition.
 *
 * @author Wouter de Jong <wouter@wouterj.nl>
 *
 * @internal
 */
final class Replace_Decorated_Remember_Me_Handler_Pass implements Compiler_Pass_Interface
{
    private const HANDLER_TAG = 'security.remember_me_handler';
    public function process(Container_Builder $container): void
    {
        $handled_firewalls = [];
        foreach ($container->find_tagged_service_ids(self::HANDLER_TAG) as $definition_id => $remember_me_handler_tags) {
            $definition = $container->find_definition($definition_id);
            if (Decorated_Remember_Me_Handler::class !== $definition->get_class()) {
                continue;
            }
            // get the actual custom remember me handler definition (passed to the decorator)
            $real_remember_me_handler = $container->find_definition((string) $definition->get_argument(0));
            foreach ($remember_me_handler_tags as $remember_me_handler_tag) {
                // some custom handlers may be used on multiple firewalls in the same application
                if (\in_array($remember_me_handler_tag['firewall'], $handled_firewalls, true)) {
                    continue;
                }
                $remember_me_handler = clone $real_remember_me_handler;
                $remember_me_handler->add_tag(self::HANDLER_TAG, $remember_me_handler_tag);
                $container->set_definition('security.authenticator.remember_me_handler.' . $remember_me_handler_tag['firewall'], $remember_me_handler);
                $handled_firewalls[] = $remember_me_handler_tag['firewall'];
            }
        }
    }
}