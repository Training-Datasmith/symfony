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
namespace Symfony\Component\Http_Kernel\Controller_Metadata;

/**
 * Builds {@see ArgumentMetadata} objects based on the given Controller.
 *
 * @author Iltar van der Berg <kjarli@gmail.com>
 */
final class Argument_Metadata_Factory implements Argument_Metadata_Factory_Interface
{
    public function create_argument_metadata(string|object|array $controller, ?\Reflection_Function_Abstract $reflector = null): array
    {
        $arguments = [];
        $reflector ??= new \ReflectionFunction($controller(...));
        $controller_name = $this->get_pretty_name($reflector);
        foreach ($reflector->get_parameters() as $param) {
            $attributes = [];
            foreach ($param->get_attributes() as $reflection_attribute) {
                if (class_exists($reflection_attribute->get_name())) {
                    $attributes[] = $reflection_attribute->new_instance();
                }
            }
            $arguments[] = new Argument_Metadata($param->get_name(), $this->get_type($param), $param->is_variadic(), $param->is_default_value_available(), $param->is_default_value_available() ? $param->get_default_value() : null, $param->allows_null(), $attributes, $controller_name);
        }
        return $arguments;
    }
    /**
     * Returns an associated type to the given parameter if available.
     */
    private function get_type(\ReflectionParameter $parameter): ?string
    {
        if (!$type = $parameter->get_type()) {
            return null;
        }
        $name = $type instanceof \ReflectionNamedType ? $type->get_name() : (string) $type;
        return match (strtolower($name)) {
            'self' => $parameter->get_declaring_class()?->name,
            'parent' => get_parent_class($parameter->get_declaring_class()?->name ?? '') ?: null,
            default => $name,
        };
    }
    private function get_pretty_name(\Reflection_Function_Abstract $r): string
    {
        $name = $r->name;
        if ($r instanceof \ReflectionMethod) {
            return $r->class . '::' . $name;
        }
        if ($r->is_anonymous() || !$class = $r->get_closure_called_class()) {
            return $name;
        }
        return $class->name . '::' . $name;
    }
}