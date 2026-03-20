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
namespace Symfony\Component\Http_Kernel\Dependency_Injection;

use Symfony\Component\Dependency_Injection\Compiler\Compiler_Pass_Interface;
use Symfony\Component\Dependency_Injection\Compiler\Service_Locator_Tag_Pass;
use Symfony\Component\Dependency_Injection\Container_Builder;
use Symfony\Component\Dependency_Injection\Exception\InvalidArgumentException;
use Symfony\Component\Dependency_Injection\Reference;
use Symfony\Component\Http_Kernel\Fragment\Fragment_Renderer_Interface;
/**
 * Adds services tagged kernel.fragment_renderer as HTTP content rendering strategies.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class Fragment_Renderer_Pass implements Compiler_Pass_Interface
{
    public function process(Container_Builder $container): void
    {
        if (!$container->has_definition('fragment.handler')) {
            return;
        }
        $definition = $container->get_definition('fragment.handler');
        $renderers = [];
        foreach ($container->find_tagged_service_ids('kernel.fragment_renderer', true) as $id => $tags) {
            $def = $container->get_definition($id);
            $class = $container->get_parameter_bag()->resolve_value($def->get_class());
            if (!$r = $container->get_reflection_class($class)) {
                throw new InvalidArgumentException(\sprintf('Class "%s" used for service "%s" cannot be found.', $class, $id));
            }
            if (!$r->is_subclass_of(Fragment_Renderer_Interface::class)) {
                throw new InvalidArgumentException(\sprintf('Service "%s" must implement interface "%s".', $id, Fragment_Renderer_Interface::class));
            }
            foreach ($tags as $tag) {
                $renderers[$tag['alias']] = new Reference($id);
            }
        }
        $definition->replace_argument(0, Service_Locator_Tag_Pass::register($container, $renderers));
    }
}