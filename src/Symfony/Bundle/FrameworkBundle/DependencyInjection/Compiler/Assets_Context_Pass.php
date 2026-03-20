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
use Symfony\Component\Dependency_Injection\Container_Builder;
use Symfony\Component\Dependency_Injection\Definition;
use Symfony\Component\Dependency_Injection\Reference;
class Assets_Context_Pass implements Compiler_Pass_Interface
{
    public function process(Container_Builder $container): void
    {
        if (!$container->has_definition('assets.context')) {
            return;
        }
        if (!$container->has_definition('router.request_context')) {
            $container->set_parameter('asset.request_context.base_path', $container->get_parameter('asset.request_context.base_path') ?? '');
            $container->set_parameter('asset.request_context.secure', $container->get_parameter('asset.request_context.secure') ?? false);
            return;
        }
        $context = $container->get_definition('assets.context');
        if (null === $container->get_parameter('asset.request_context.base_path')) {
            $context->replace_argument(1, (new Definition('string'))->set_factory([new Reference('router.request_context'), 'getBaseUrl']));
        }
        if (null === $container->get_parameter('asset.request_context.secure')) {
            $context->replace_argument(2, (new Definition('bool'))->set_factory([new Reference('router.request_context'), 'isSecure']));
        }
    }
}