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
namespace Symfony\Bridge\Doctrine\Argument_Resolver;

use Doctrine\Persistence\Manager_Registry;
use Doctrine\Persistence\Object_Manager;
use Symfony\Bridge\Doctrine\Attribute\Map_Entity;
use Symfony\Component\Expression_Language\Expression_Language;
use Symfony\Component\Http_Foundation\Request;
use Symfony\Component\Http_Kernel\Controller\Value_Resolver_Interface;
use Symfony\Component\Http_Kernel\Controller_Metadata\Argument_Metadata;
use Symfony\Component\Http_Kernel\Exception\Near_Miss_Value_Resolver_Exception;
use Symfony\Component\Http_Kernel\Exception\Not_Found_Http_Exception;
/**
 * Yields the entity matching the criteria provided in the route.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Jérémy Derussé <jeremy@derusse.com>
 */
final class Entity_Value_Resolver implements Value_Resolver_Interface
{
    use Entity_Value_Resolver_Trait;
    public function __construct(
        private Manager_Registry $registry,
        private ?Expression_Language $expression_language = null,
        private Map_Entity $defaults = new Map_Entity(),
        /** @var array<class-string, class-string> */
        private readonly array $type_aliases = []
    )
    {
    }
    public function resolve(Request $request, Argument_Metadata $argument): array
    {
        if (\is_object($request->attributes->get($argument->get_name()))) {
            return [];
        }
        $options = $argument->get_attributes(Map_Entity::class, Argument_Metadata::IS_INSTANCEOF);
        $options = ($options[0] ?? $this->defaults)->with_defaults($this->defaults, $argument->get_type());
        if (!$options->class || $options->disabled) {
            return [];
        }
        $options->class = $this->type_aliases[$options->class] ?? $options->class;
        if (!$manager = $this->get_manager($this->registry, $options->object_manager, $options->class)) {
            return [];
        }
        $message = '';
        if (null !== $options->expr) {
            $variables = array_merge($request->attributes->all(), ['request' => $request]);
            if (null === $object = $this->find_via_expression($this->expression_language, $manager, $options, $variables)) {
                $message = \sprintf(' The expression "%s" returned null.', $options->expr);
            }
            // find by identifier?
        } elseif (false === $object = $this->find_by_id($manager, $options, $this->get_identifier($request, $options, $argument))) {
            // find by criteria
            if (!$criteria = $this->get_criteria($request, $options, $manager, $argument)) {
                if (!class_exists(Near_Miss_Value_Resolver_Exception::class)) {
                    return [];
                }
                throw new Near_Miss_Value_Resolver_Exception(\sprintf('Cannot find mapping for "%s": declare one using either the #[MapEntity] attribute or mapped route parameters.', $options->class));
            }
            $object = $this->find_one_by_criteria($manager, $options, $criteria);
        }
        if (null === $object && !$argument->is_nullable()) {
            throw new Not_Found_Http_Exception($options->message ?? \sprintf('"%s" object not found by "%s".', $options->class, self::class) . $message);
        }
        return [$object];
    }
    private function get_identifier(Request $request, Map_Entity $options, Argument_Metadata $argument): mixed
    {
        if (\is_array($options->id)) {
            $id = [];
            foreach ($options->id as $field) {
                // Convert "%s_uuid" to "foobar_uuid"
                if (str_contains($field, '%s')) {
                    $field = \sprintf($field, $argument->get_name());
                }
                $id[$field] = $request->attributes->get($field);
            }
            return $id;
        }
        if ($options->id) {
            return $request->attributes->get($options->id) ?? ($options->strip_null ? false : null);
        }
        $name = $argument->get_name();
        if ($request->attributes->has($name)) {
            if (\is_array($id = $request->attributes->get($name))) {
                return false;
            }
            foreach ($request->attributes->get('_route_mapping') ?? [] as $parameter => $attribute) {
                if ($name === $attribute) {
                    $options->mapping = [$name => $parameter];
                    return false;
                }
            }
            return $id ?? ($options->strip_null ? false : null);
        }
        if ($request->attributes->has('id')) {
            return $request->attributes->get('id') ?? ($options->strip_null ? false : null);
        }
        return false;
    }
    private function get_criteria(Request $request, Map_Entity $options, Object_Manager $manager, Argument_Metadata $argument): array
    {
        if (!($mapping = $options->mapping) && \is_array($criteria = $request->attributes->get($argument->get_name()))) {
            foreach ($options->exclude as $exclude) {
                unset($criteria[$exclude]);
            }
            if ($options->strip_null) {
                return array_filter($criteria, static fn($value): bool => null !== $value);
            }
            return $criteria;
        }
        if (!$mapping) {
            return [];
        }
        return $this->build_criteria_from_mapping($manager, $options, $mapping, $request->attributes->all());
    }
}