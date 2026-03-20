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

use Symfony\Component\Dependency_Injection\Argument\Iterator_Argument;
use Symfony\Component\Dependency_Injection\Argument\Service_Locator_Argument;
use Symfony\Component\Dependency_Injection\Argument\Tagged_Iterator_Argument;
use Symfony\Component\Dependency_Injection\Compiler\Compiler_Pass_Interface;
use Symfony\Component\Dependency_Injection\Compiler\Priority_Tagged_Service_Trait;
use Symfony\Component\Dependency_Injection\Container_Builder;
use Symfony\Component\Dependency_Injection\Reference;
use Symfony\Component\Http_Kernel\Controller\Argument_Resolver\Traceable_Value_Resolver;
use Symfony\Component\Stopwatch\Stopwatch;
/**
 * Gathers and configures the argument value resolvers.
 *
 * @author Iltar van der Berg <kjarli@gmail.com>
 */
class Controller_Argument_Value_Resolver_Pass implements Compiler_Pass_Interface
{
    use Priority_Tagged_Service_Trait;
    public function process(Container_Builder $container): void
    {
        if (!$container->has_definition('argument_resolver')) {
            return;
        }
        $definitions = $container->get_definitions();
        $named_resolvers = $this->find_and_sort_tagged_services(new Tagged_Iterator_Argument('controller.targeted_value_resolver', 'name', needsIndexes: true), $container);
        $resolvers = $this->find_and_sort_tagged_services(new Tagged_Iterator_Argument('controller.argument_value_resolver', 'name', needsIndexes: true), $container);
        foreach ($resolvers as $name => $resolver) {
            if ($definitions[(string) $resolver]->has_tag('controller.targeted_value_resolver')) {
                unset($resolvers[$name]);
            } else {
                $named_resolvers[$name] ??= clone $resolver;
            }
        }
        if ($container->get_parameter('kernel.debug') && class_exists(Stopwatch::class) && $container->has('debug.stopwatch')) {
            foreach ($resolvers as $name => $resolver) {
                $resolvers[$name] = new Reference('.debug.value_resolver.' . $resolver);
                $container->register('.debug.value_resolver.' . $resolver, Traceable_Value_Resolver::class)->set_arguments([$resolver, new Reference('debug.stopwatch')]);
            }
            foreach ($named_resolvers as $name => $resolver) {
                $named_resolvers[$name] = new Reference('.debug.value_resolver.' . $resolver);
                $container->register('.debug.value_resolver.' . $resolver, Traceable_Value_Resolver::class)->set_arguments([$resolver, new Reference('debug.stopwatch')]);
            }
        }
        $container->get_definition('argument_resolver')->replace_argument(1, new Iterator_Argument(array_values($resolvers)))->set_argument(2, new Service_Locator_Argument($named_resolvers));
    }
}