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
use Symfony\Component\Dependency_Injection\Compiler\Priority_Tagged_Service_Trait;
use Symfony\Component\Dependency_Injection\Container_Builder;
/**
 * Adds tagged twig.extension services to twig service.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class Twig_Environment_Pass implements Compiler_Pass_Interface
{
    use Priority_Tagged_Service_Trait;
    public function process(Container_Builder $container): void
    {
        if (false === $container->has_definition('twig')) {
            return;
        }
        $definition = $container->get_definition('twig');
        // Extensions must always be registered before everything else.
        // For instance, global variable definitions must be registered
        // afterward. If not, the globals from the extensions will never
        // be registered.
        $current_method_calls = $definition->get_method_calls();
        $twig_bridge_extensions_method_calls = [];
        $others_extensions_method_calls = [];
        foreach ($this->find_and_sort_tagged_services('twig.extension', $container) as $extension) {
            $method_call = ['addExtension', [$extension]];
            $extension_class = $container->get_definition((string) $extension)->get_class();
            if (\is_string($extension_class) && str_starts_with($extension_class, 'Symfony\Bridge\Twig\Extension')) {
                $twig_bridge_extensions_method_calls[] = $method_call;
            } else {
                $others_extensions_method_calls[] = $method_call;
            }
        }
        if ($twig_bridge_extensions_method_calls || $others_extensions_method_calls) {
            $definition->set_method_calls(array_merge($twig_bridge_extensions_method_calls, $others_extensions_method_calls, $current_method_calls));
        }
    }
}