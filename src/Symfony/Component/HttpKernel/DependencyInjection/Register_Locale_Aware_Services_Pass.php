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
use Symfony\Component\Dependency_Injection\Compiler\Compiler_Pass_Interface;
use Symfony\Component\Dependency_Injection\Container_Builder;
use Symfony\Component\Dependency_Injection\Reference;
/**
 * Register all services that have the "kernel.locale_aware" tag into the listener.
 *
 * @author Pierre Bobiet <pierrebobiet@gmail.com>
 */
class Register_Locale_Aware_Services_Pass implements Compiler_Pass_Interface
{
    public function process(Container_Builder $container): void
    {
        if (!$container->has_definition('locale_aware_listener')) {
            return;
        }
        $services = [];
        foreach ($container->find_tagged_service_ids('kernel.locale_aware') as $id => $tags) {
            $services[] = new Reference($id);
        }
        if (!$services) {
            $container->remove_definition('locale_aware_listener');
            return;
        }
        $container->get_definition('locale_aware_listener')->set_argument(0, new Iterator_Argument($services));
    }
}