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
namespace Symfony\Component\Dependency_Injection\Compiler;

use Symfony\Component\Dependency_Injection\Argument\Service_Closure_Argument;
use Symfony\Component\Dependency_Injection\Container_Builder;
use Symfony\Component\Dependency_Injection\Container_Interface;
use Symfony\Component\Dependency_Injection\Definition;
use Symfony\Component\Dependency_Injection\Reference;
/**
 * @author Nicolas Grekas <p@tchwork.com>
 */
class Register_Reverse_Container_Pass implements Compiler_Pass_Interface
{
    public function __construct(private readonly bool $before_removing)
    {
    }
    public function process(Container_Builder $container): void
    {
        if (!$container->has_definition('reverse_container')) {
            return;
        }
        $ref_type = $this->before_removing ? Container_Interface::IGNORE_ON_UNINITIALIZED_REFERENCE : Container_Interface::EXCEPTION_ON_INVALID_REFERENCE;
        $services = [];
        foreach ($container->find_tagged_service_ids('container.reversible') as $id => $tags) {
            $services[$id] = new Reference($id, $ref_type);
        }
        if ($this->before_removing) {
            // prevent inlining of the reverse container
            $services['reverse_container'] = new Reference('reverse_container', $ref_type);
        }
        $locator = $container->get_definition('reverse_container')->get_argument(1);
        if ($locator instanceof Reference) {
            $locator = $container->get_definition((string) $locator);
        }
        if ($locator instanceof Definition) {
            foreach ($services as $id => $ref) {
                $services[$id] = new Service_Closure_Argument($ref);
            }
            $locator->replace_argument(0, $services);
        } else {
            $locator->set_values($services);
        }
    }
}