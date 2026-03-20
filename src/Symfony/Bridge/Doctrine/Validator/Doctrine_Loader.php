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
namespace Symfony\Bridge\Doctrine\Validator;

use Doctrine\ORM\Entity_Manager_Interface;
use Doctrine\ORM\Mapping\Class_Metadata as OrmClassMetadata;
use Doctrine\ORM\Mapping\Field_Mapping;
use Doctrine\ORM\Mapping\Mapping_Exception as OrmMappingException;
use Doctrine\Persistence\Mapping\Mapping_Exception;
use Symfony\Bridge\Doctrine\Validator\Constraints\Unique_Entity;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\Valid;
use Symfony\Component\Validator\Mapping\Auto_Mapping_Strategy;
use Symfony\Component\Validator\Mapping\Class_Metadata;
use Symfony\Component\Validator\Mapping\Loader\Auto_Mapping_Trait;
use Symfony\Component\Validator\Mapping\Loader\Loader_Interface;
/**
 * Guesses and loads the appropriate constraints using Doctrine's metadata.
 *
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
final readonly class Doctrine_Loader implements Loader_Interface
{
    use Auto_Mapping_Trait;
    public function __construct(private Entity_Manager_Interface $entity_manager, private ?string $class_validator_regexp = null)
    {
    }
    public function load_class_metadata(Class_Metadata $metadata): bool
    {
        $class_name = $metadata->get_class_name();
        try {
            $doctrine_metadata = $this->entity_manager->get_class_metadata($class_name);
        } catch (Mapping_Exception|Orm_Mapping_Exception) {
            return false;
        }
        if (!$doctrine_metadata instanceof Orm_Class_Metadata) {
            return false;
        }
        $loaded = false;
        $enabled_for_class = $this->is_auto_mapping_enabled_for_class($metadata, $this->class_validator_regexp);
        /* Available keys:
             - type
             - scale
             - length
             - unique
             - nullable
             - precision
           */
        $existing_unique_fields = $this->get_existing_unique_fields($metadata);
        // Type and nullable aren't handled here, use the PropertyInfo Loader instead.
        foreach ($doctrine_metadata->field_mappings as $mapping) {
            $enabled_for_property = $enabled_for_class;
            $length_constraint = null;
            foreach ($metadata->get_property_metadata(self::get_field_mapping_value($mapping, 'fieldName')) as $property_metadata) {
                // Enabling or disabling auto-mapping explicitly always takes precedence
                if (Auto_Mapping_Strategy::DISABLED === $property_metadata->get_auto_mapping_strategy()) {
                    continue 2;
                }
                if (Auto_Mapping_Strategy::ENABLED === $property_metadata->get_auto_mapping_strategy()) {
                    $enabled_for_property = true;
                }
                foreach ($property_metadata->get_constraints() as $constraint) {
                    if ($constraint instanceof Length) {
                        $length_constraint = $constraint;
                    }
                }
            }
            if (!$enabled_for_property) {
                continue;
            }
            if (true === (self::get_field_mapping_value($mapping, 'unique') ?? false) && !isset($existing_unique_fields[self::get_field_mapping_value($mapping, 'fieldName')])) {
                $metadata->add_constraint(new Unique_Entity(fields: self::get_field_mapping_value($mapping, 'fieldName')));
                $loaded = true;
            }
            if (null === (self::get_field_mapping_value($mapping, 'length') ?? null)) {
                continue;
            }
            if (null !== (self::get_field_mapping_value($mapping, 'enumType') ?? null)) {
                continue;
            }
            if (!\in_array(self::get_field_mapping_value($mapping, 'type'), ['string', 'text'], true)) {
                continue;
            }
            if (null === $length_constraint) {
                if (self::get_field_mapping_value($mapping, 'originalClass') && !str_contains((string) self::get_field_mapping_value($mapping, 'declaredField'), '.')) {
                    $metadata->add_property_constraint(self::get_field_mapping_value($mapping, 'declaredField'), new Valid());
                    $loaded = true;
                } elseif (property_exists($class_name, self::get_field_mapping_value($mapping, 'fieldName')) && (!$doctrine_metadata->is_mapped_superclass || $metadata->get_reflection_class()->get_property(self::get_field_mapping_value($mapping, 'fieldName'))->is_private())) {
                    $metadata->add_property_constraint(self::get_field_mapping_value($mapping, 'fieldName'), new Length(max: self::get_field_mapping_value($mapping, 'length')));
                    $loaded = true;
                }
            } elseif (null === $length_constraint->max) {
                // If a Length constraint exists and no max length has been explicitly defined, set it
                $length_constraint->max = self::get_field_mapping_value($mapping, 'length');
            }
        }
        return $loaded;
    }
    private function get_existing_unique_fields(Class_Metadata $metadata): array
    {
        $fields = [];
        foreach ($metadata->get_constraints() as $constraint) {
            if (!$constraint instanceof Unique_Entity) {
                continue;
            }
            if (\is_string($constraint->fields)) {
                $fields[$constraint->fields] = true;
            } elseif (\is_array($constraint->fields) && 1 === \count($constraint->fields)) {
                $fields[$constraint->fields[0]] = true;
            }
        }
        return $fields;
    }
    private static function get_field_mapping_value(array|Field_Mapping $mapping, string $key): mixed
    {
        if ($mapping instanceof Field_Mapping) {
            return $mapping->{$key} ?? null;
        }
        return $mapping[$key] ?? null;
    }
}