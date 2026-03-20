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
namespace Symfony\Component\Http_Kernel\Controller\Argument_Resolver;

use Symfony\Component\Http_Foundation\Request;
use Symfony\Component\Http_Kernel\Attribute\Map_Query_Parameter;
use Symfony\Component\Http_Kernel\Controller\Value_Resolver_Interface;
use Symfony\Component\Http_Kernel\Controller_Metadata\Argument_Metadata;
use Symfony\Component\Http_Kernel\Exception\Http_Exception;
use Symfony\Component\Uid\Abstract_Uid;
/**
 * Resolve arguments of type: array, string, int, float, bool, \BackedEnum from query parameters.
 *
 * @author Ruud Kamphuis <ruud@ticketswap.com>
 * @author Nicolas Grekas <p@tchwork.com>
 * @author Mateusz Anders <anders_mateusz@outlook.com>
 * @author Ionut Enache <i.ovidiuenache@yahoo.com>
 */
final class Query_Parameter_Value_Resolver implements Value_Resolver_Interface
{
    public function resolve(Request $request, Argument_Metadata $argument): array
    {
        if (!$attribute = $argument->get_attributes_of_type(Map_Query_Parameter::class)[0] ?? null) {
            return [];
        }
        $name = $attribute->name ?? $argument->get_name();
        $validation_failed_code = $attribute->validation_failed_status_code;
        if (!$request->query->has($name)) {
            if ($argument->is_nullable() || $argument->has_default_value()) {
                return [];
            }
            throw Http_Exception::from_status_code($validation_failed_code, \sprintf('Missing query parameter "%s".', $name));
        }
        $value = $request->query->all()[$name];
        $type = $argument->get_type();
        if (null === $attribute->filter && 'array' === $type) {
            if (!$argument->is_variadic()) {
                return [(array) $value];
            }
            $filtered = array_values(array_filter((array) $value, \is_array(...)));
            if ($filtered !== $value && !($attribute->flags & \FILTER_NULL_ON_FAILURE)) {
                throw Http_Exception::from_status_code($validation_failed_code, \sprintf('Invalid query parameter "%s".', $name));
            }
            return $filtered;
        }
        $options = ['flags' => $attribute->flags | \FILTER_NULL_ON_FAILURE, 'options' => $attribute->options];
        if ('array' === $type || $argument->is_variadic()) {
            $value = (array) $value;
            $options['flags'] |= \FILTER_REQUIRE_ARRAY;
        } else {
            $options['flags'] |= \FILTER_REQUIRE_SCALAR;
        }
        $uid_type = null;
        if (is_subclass_of($type, Abstract_Uid::class)) {
            $uid_type = $type;
            $type = 'uid';
        }
        $enum_type = null;
        $filter = match ($type) {
            'array' => \FILTER_DEFAULT,
            'string' => isset($attribute->options['regexp']) ? \FILTER_VALIDATE_REGEXP : \FILTER_DEFAULT,
            'int' => \FILTER_VALIDATE_INT,
            'float' => \FILTER_VALIDATE_FLOAT,
            'bool' => \FILTER_VALIDATE_BOOL,
            'uid' => \FILTER_DEFAULT,
            default => match ($enum_type = is_subclass_of($type, \Backed_Enum::class) ? (new \Reflection_Enum($type))->get_backing_type()->get_name() : null) {
                'int' => \FILTER_VALIDATE_INT,
                'string' => \FILTER_DEFAULT,
                default => throw new \LogicException(\sprintf('#[MapQueryParameter] cannot be used on controller argument "%s$%s" of type "%s"; one of array, string, int, float, bool, uid or \BackedEnum should be used.', $argument->is_variadic() ? '...' : '', $argument->get_name(), $type ?? 'mixed')),
            },
        };
        $value = filter_var($value, $attribute->filter ?? $filter, $options);
        if (null !== $enum_type && null !== $value) {
            $enum_from = static function ($value) use ($type) {
                if (!\is_string($value) && !\is_int($value)) {
                    return null;
                }
                try {
                    return $type::from($value);
                } catch (\Value_Error) {
                    return null;
                }
            };
            $value = \is_array($value) ? array_map($enum_from, $value) : $enum_from($value);
        }
        if (null !== $uid_type) {
            $value = \is_array($value) ? array_map([$uid_type, 'fromString'], $value) : $uid_type::from_string($value);
        }
        if (null === $value && !($attribute->flags & \FILTER_NULL_ON_FAILURE)) {
            throw Http_Exception::from_status_code($validation_failed_code, \sprintf('Invalid query parameter "%s".', $name));
        }
        if (!\is_array($value)) {
            return [$value];
        }
        $filtered = array_filter($value, static fn($v): bool => null !== $v);
        if ($argument->is_variadic()) {
            $filtered = array_values($filtered);
        }
        if ($filtered !== $value && !($attribute->flags & \FILTER_NULL_ON_FAILURE)) {
            throw Http_Exception::from_status_code($validation_failed_code, \sprintf('Invalid query parameter "%s".', $name));
        }
        return $argument->is_variadic() ? $filtered : [$filtered];
    }
}