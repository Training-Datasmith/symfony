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
namespace Symfony\Bridge\Doctrine\Argument_Resolver\Console;

use Doctrine\Persistence\Manager_Registry;
use Doctrine\Persistence\Object_Manager;
use Symfony\Bridge\Doctrine\Argument_Resolver\Entity_Value_Resolver_Trait;
use Symfony\Bridge\Doctrine\Attribute\Map_Entity;
use Symfony\Component\Console\Argument_Resolver\Exception\Near_Miss_Value_Resolver_Exception;
use Symfony\Component\Console\Argument_Resolver\Value_Resolver\Value_Resolver_Interface;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Attribute\Reflection\Reflection_Member;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Input\Input_Interface;
use Symfony\Component\Expression_Language\Expression_Language;
use Symfony\Component\String\Unicode_String;
/**
 * Resolves a Command parameter holding the #[MapEntity] attribute to an Entity.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Jérémy Derussé <jeremy@derusse.com>
 * @author Robin Chalas <robin.chalas@gmail.com>
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
    public function resolve(string $argument_name, Input_Interface $input, Reflection_Member $member): iterable
    {
        if (!Argument::try_from($member->get_member()) && !Option::try_from($member->get_member())) {
            return [];
        }
        $type = $member->get_type();
        if (!$type instanceof \ReflectionNamedType || $type->is_builtin()) {
            return [];
        }
        $input_name = $member->get_input_name();
        if ($input->has_argument($input_name) && \is_object($input->get_argument($input_name))) {
            return [];
        }
        // #[MapEntity] is optional
        $attribute = $member->get_attribute(Map_Entity::class) ?? $this->defaults;
        $options = $attribute->with_defaults($this->defaults, $type->get_name());
        if (!$options->class) {
            return [];
        }
        $options->class = $this->type_aliases[$options->class] ?? $options->class;
        if (!$manager = $this->get_manager($this->registry, $options->object_manager, $options->class)) {
            return [];
        }
        $message = '';
        if (null !== $options->expr) {
            $variables = array_merge($input->get_arguments(), ['input' => $input]);
            if (null === $object = $this->find_via_expression($this->expression_language, $manager, $options, $variables)) {
                $message = \sprintf(' The expression "%s" returned null.', $options->expr);
            }
        } elseif (false === $object = $this->find_by_id($manager, $options, $this->get_identifier($input_name, $input, $options))) {
            if (!$criteria = $this->get_criteria($input_name, $input, $options, $manager)) {
                throw new Near_Miss_Value_Resolver_Exception(\sprintf('Cannot find mapping for "%s": use the #[MapEntity] attribute to configure entity resolution.', $options->class));
            }
            $object = $this->find_one_by_criteria($manager, $options, $criteria);
        }
        if (null === $object && !$member->is_nullable()) {
            throw new RuntimeException($options->message ?? \sprintf('"%s" object not found by "%s".%s', $options->class, self::class, $message));
        }
        return [$object];
    }
    private function get_identifier(string $argument_name, Input_Interface $input, Map_Entity $options): mixed
    {
        if (\is_array($options->id)) {
            $id = [];
            foreach ($options->id as $field) {
                if (str_contains($field, '%s')) {
                    $field = \sprintf($field, $argument_name);
                }
                $field_name = (new Unicode_String($field))->kebab()->to_string();
                if (!$input->has_argument($field_name)) {
                    return $options->strip_null ? false : null;
                }
                $id[$field] = $input->get_argument($field_name);
            }
            return $id;
        }
        if ($options->id) {
            $id_name = (new Unicode_String($options->id))->kebab()->to_string();
            return $input->has_argument($id_name) ? $input->get_argument($id_name) : ($options->strip_null ? false : null);
        }
        if ($input->has_argument($argument_name)) {
            $value = $input->get_argument($argument_name);
            if (\is_array($value)) {
                return false;
            }
            return $value ?? ($options->strip_null ? false : null);
        }
        if ($input->has_argument('id')) {
            return $input->get_argument('id') ?? ($options->strip_null ? false : null);
        }
        return false;
    }
    private function get_criteria(string $argument_name, Input_Interface $input, Map_Entity $options, Object_Manager $manager): array
    {
        $mapping = $options->mapping;
        if (!$mapping && $input->has_argument($argument_name) && \is_array($criteria = $input->get_argument($argument_name))) {
            foreach ($options->exclude ?? [] as $exclude) {
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
        if (array_is_list($mapping)) {
            /** @var list<string> $list */
            $list = $mapping;
            $mapping = array_combine($list, $list);
        }
        $values = [];
        foreach (array_keys($mapping) as $attribute) {
            $attribute_name = (new Unicode_String($attribute))->kebab()->to_string();
            if ($input->has_argument($attribute_name)) {
                $values[$attribute] = $input->get_argument($attribute_name);
            }
        }
        return $this->build_criteria_from_mapping($manager, $options, $mapping, $values);
    }
}