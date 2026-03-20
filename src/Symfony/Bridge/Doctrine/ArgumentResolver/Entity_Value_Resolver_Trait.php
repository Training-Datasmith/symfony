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

use Doctrine\DBAL\Types\Conversion_Exception;
use Doctrine\ORM\Entity_Manager_Interface;
use Doctrine\ORM\No_Result_Exception;
use Doctrine\Persistence\Manager_Registry;
use Doctrine\Persistence\Object_Manager;
use Symfony\Bridge\Doctrine\Attribute\Map_Entity;
use Symfony\Component\Expression_Language\Expression_Language;
/**
 * Provides common entity resolution logic for both HTTP and Console value resolvers.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Jérémy Derussé <jeremy@derusse.com>
 * @author Robin Chalas <robin.chalas@gmail.com>
 *
 * @internal
 */
trait Entity_Value_Resolver_Trait
{
    /**
     * Gets the entity manager for the given class.
     */
    private function get_manager(Manager_Registry $registry, ?string $name, string $class): ?Object_Manager
    {
        if (null === $name) {
            return $registry->get_manager_for_class($class);
        }
        try {
            $manager = $registry->get_manager($name);
        } catch (\InvalidArgumentException) {
            return null;
        }
        return $manager->get_metadata_factory()->is_transient($class) ? null : $manager;
    }
    /**
     * Finds an entity by its identifier.
     *
     * @return false|object|null false when mapping/exclude are set, null when not found, object when found
     */
    private function find_by_id(Object_Manager $manager, Map_Entity $options, mixed $id): false|object|null
    {
        if ($options->mapping || $options->exclude) {
            return false;
        }
        if (false === $id || null === $id) {
            return $id;
        }
        if (\is_array($id) && \in_array(null, $id, true)) {
            return null;
        }
        if ($options->evict_cache && $manager instanceof Entity_Manager_Interface) {
            $cache_provider = $manager->get_cache();
            if ($cache_provider && $cache_provider->contains_entity($options->class, $id)) {
                $cache_provider->evict_entity($options->class, $id);
            }
        }
        try {
            return $manager->get_repository($options->class)->find($id);
        } catch (No_Result_Exception|Conversion_Exception) {
            return null;
        }
    }
    /**
     * Finds an entity via expression language.
     */
    private function find_via_expression(?Expression_Language $expression_language, Object_Manager $manager, Map_Entity $options, array $variables): object|iterable|null
    {
        if (!$expression_language) {
            throw new \LogicException(\sprintf('You cannot use the "%s" if the ExpressionLanguage component is not available. Try running "composer require symfony/expression-language".', static::class));
        }
        $repository = $manager->get_repository($options->class);
        $variables['repository'] = $repository;
        try {
            return $expression_language->evaluate($options->expr, $variables);
        } catch (No_Result_Exception|Conversion_Exception) {
            return null;
        }
    }
    /**
     * Finds an entity by criteria.
     */
    private function find_one_by_criteria(Object_Manager $manager, Map_Entity $options, array $criteria): ?object
    {
        try {
            return $manager->get_repository($options->class)->find_one_by($criteria);
        } catch (No_Result_Exception|Conversion_Exception) {
            return null;
        }
    }
    /**
     * Builds criteria from mapping configuration.
     */
    private function build_criteria_from_mapping(Object_Manager $manager, Map_Entity $options, array $mapping, array $values): array
    {
        if (array_is_list($mapping)) {
            $mapping = array_combine($mapping, $mapping);
        }
        foreach ($options->exclude ?? [] as $exclude) {
            unset($mapping[$exclude]);
        }
        if (!$mapping) {
            return [];
        }
        $criteria = [];
        $metadata = null === $options->mapping ? $manager->get_class_metadata($options->class) : false;
        foreach ($mapping as $attribute => $field) {
            if ($metadata && !$metadata->has_field($field) && (!$metadata->has_association($field) || !$metadata->is_single_valued_association($field))) {
                continue;
            }
            if (!\array_key_exists($attribute, $values)) {
                continue;
            }
            $criteria[$field] = $values[$attribute];
        }
        if ($options->strip_null) {
            return array_filter($criteria, static fn($value): bool => null !== $value);
        }
        return $criteria;
    }
}