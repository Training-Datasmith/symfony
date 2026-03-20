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

use Symfony\Component\Dependency_Injection\Child_Definition;
use Symfony\Component\Dependency_Injection\Container_Builder;
use Symfony\Component\Dependency_Injection\Exception\InvalidArgumentException;
/**
 * @author Nicolas Grekas <p@tchwork.com>
 */
class Resolve_Class_Pass implements Compiler_Pass_Interface
{
    public function process(Container_Builder $container): void
    {
        foreach ($container->get_definitions() as $id => $definition) {
            if ($definition->is_synthetic()) {
                continue;
            }
            if ($definition->has_errors()) {
                continue;
            }
            if (null !== $definition->get_class()) {
                continue;
            }
            if (!preg_match('/^[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*+(?:\\\\[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*+)++$/', $id)) {
                continue;
            }
            if ($container->get_reflection_class($id, false)) {
                $definition->set_class($id);
                continue;
            }
            if ($definition instanceof Child_Definition) {
                throw new InvalidArgumentException(\sprintf('Service definition "%s" has a parent but no class, and its name looks like a FQCN. Either the class is missing or you want to inherit it from the parent service. To resolve this ambiguity, please rename this service to a non-FQCN (e.g. using dots), or create the missing class.', $id));
            }
            throw new InvalidArgumentException(\sprintf('Service id "%s" looks like a FQCN but no corresponding class or interface exists. To resolve this ambiguity, please rename this service to a non-FQCN (e.g. using dots), or create the missing class or interface.', $id));
        }
    }
}