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

use Symfony\Component\Dependency_Injection\Container_Builder;
use Symfony\Component\Dependency_Injection\Exception\RuntimeException;
/**
 * This pass validates aliases, it provides the following checks:
 *
 * - An alias which happens to be an interface must resolve to a service implementing this interface. This ensures injecting the aliased interface won't cause a type error at runtime.
 */
class Check_Alias_Validity_Pass implements Compiler_Pass_Interface
{
    public function process(Container_Builder $container): void
    {
        foreach ($container->get_aliases() as $id => $alias) {
            try {
                if (!$container->has_definition((string) $alias)) {
                    continue;
                }
                $target = $container->get_definition((string) $alias);
                if (null === $target->get_class()) {
                    continue;
                }
                if (null !== $target->get_factory()) {
                    continue;
                }
                $reflection = $container->get_reflection_class($id);
                if (null === $reflection) {
                    continue;
                }
                if (!$reflection->is_interface()) {
                    continue;
                }
                $target_reflection = $container->get_reflection_class($target->get_class());
                if (null !== $target_reflection && !$target_reflection->implements_interface($id)) {
                    throw new RuntimeException(\sprintf('Invalid alias definition: alias "%s" is referencing class "%s" but this class does not implement "%s". Because this alias is an interface, "%s" must implement "%s".', $id, $target->get_class(), $id, $target->get_class(), $id));
                }
            } catch (\Reflection_Exception) {
                continue;
            }
        }
    }
}