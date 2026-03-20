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

use Symfony\Component\Dependency_Injection\Attribute\Autowire;
use Symfony\Component\Dependency_Injection\Attribute\Autowire_Decorated;
use Symfony\Component\Dependency_Injection\Attribute\Target;
use Symfony\Component\Dependency_Injection\Container_Interface;
use Symfony\Component\Dependency_Injection\Definition;
use Symfony\Component\Dependency_Injection\Exception\InvalidArgumentException;
use Symfony\Component\Dependency_Injection\Typed_Reference;
use Symfony\Contracts\Service\Attribute\Required;
/**
 * Looks for definitions with autowiring enabled and registers their corresponding "#[Required]" properties.
 *
 * @author Sebastien Morel (Plopix) <morel.seb@gmail.com>
 * @author Nicolas Grekas <p@tchwork.com>
 */
class Autowire_Required_Properties_Pass extends Abstract_Recursive_Pass
{
    protected bool $skip_scalars = true;
    protected function process_value(mixed $value, bool $is_root = false): mixed
    {
        $value = parent::process_value($value, $is_root);
        if (!$value instanceof Definition || !$value->is_autowired() || $value->is_abstract() || !$value->get_class()) {
            return $value;
        }
        if (!$reflection_class = $this->container->get_reflection_class($value->get_class(), false)) {
            return $value;
        }
        $properties = $value->get_properties();
        foreach ($reflection_class->get_properties() as $reflection_property) {
            if (!($type = $reflection_property->get_type()) instanceof \ReflectionNamedType) {
                continue;
            }
            if (!$reflection_property->get_attributes(Required::class)) {
                continue;
            }
            if (\array_key_exists($name = $reflection_property->get_name(), $properties)) {
                continue;
            }
            if ($reflection_property->is_private_set() || $reflection_property->is_protected_set() || !$reflection_property->is_public()) {
                throw new InvalidArgumentException(\sprintf('Cannot autowire non-public(set) property "%s::$%s" with #[%s].', $reflection_class->get_name(), $reflection_property->get_name(), Required::class));
            }
            $type = $type->get_name();
            $value->set_property($name, new Typed_Reference($type, $type, Container_Interface::EXCEPTION_ON_INVALID_REFERENCE, $name, array_map(static fn(\Reflection_Attribute $a): object => $a->new_instance(), array_merge($reflection_property->get_attributes(Autowire::class, \Reflection_Attribute::IS_INSTANCEOF), $reflection_property->get_attributes(Autowire_Decorated::class), $reflection_property->get_attributes(Target::class)))));
        }
        return $value;
    }
}