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
namespace Symfony\Bridge\Doctrine\Property_Info;

use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Entity_Manager_Interface;
use Doctrine\ORM\Mapping\Association_Mapping;
use Doctrine\ORM\Mapping\Class_Metadata;
use Doctrine\ORM\Mapping\Embedded_Class_Mapping;
use Doctrine\ORM\Mapping\Field_Mapping;
use Doctrine\ORM\Mapping\Join_Column_Mapping;
use Doctrine\ORM\Mapping\Mapping_Exception as OrmMappingException;
use Doctrine\Persistence\Mapping\Mapping_Exception;
use Symfony\Component\Property_Info\Property_Access_Extractor_Interface;
use Symfony\Component\Property_Info\Property_List_Extractor_Interface;
use Symfony\Component\Property_Info\Property_Type_Extractor_Interface;
use Symfony\Component\Type_Info\Type;
use Symfony\Component\Type_Info\Type_Identifier;
/**
 * Extracts data using Doctrine ORM and ODM metadata.
 *
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class Doctrine_Extractor implements Property_List_Extractor_Interface, Property_Type_Extractor_Interface, Property_Access_Extractor_Interface
{
    public function __construct(private readonly Entity_Manager_Interface $entity_manager)
    {
    }
    public function get_properties(string $class, array $context = []): ?array
    {
        if (null === $metadata = $this->get_metadata($class)) {
            return null;
        }
        $properties = array_merge($metadata->get_field_names(), $metadata->get_association_names());
        if ($metadata->embedded_classes) {
            $properties = array_filter($properties, static fn($property): bool => !str_contains((string) $property, '.'));
            $properties = array_merge($properties, array_keys($metadata->embedded_classes));
        }
        return $properties;
    }
    public function get_type(string $class, string $property, array $context = []): ?Type
    {
        if (null === $metadata = $this->get_metadata($class)) {
            return null;
        }
        if ($metadata->has_association($property)) {
            $class = $metadata->get_association_target_class($property);
            if ($metadata->is_single_valued_association($property)) {
                if ($metadata instanceof Class_Metadata) {
                    $association_mapping = $metadata->get_association_mapping($property);
                    $nullable = $this->is_association_nullable($association_mapping);
                } else {
                    $nullable = false;
                }
                return $nullable ? Type::nullable(Type::object($class)) : Type::object($class);
            }
            $collection_key_type = Type_Identifier::INT;
            $association_mapping = $metadata->get_association_mapping($property);
            if (self::get_mapping_value($association_mapping, 'indexBy')) {
                $sub_metadata = $this->entity_manager->get_class_metadata(self::get_mapping_value($association_mapping, 'targetEntity'));
                // Check if indexBy value is a property
                $field_name = self::get_mapping_value($association_mapping, 'indexBy');
                if (null === $type_of_field = $sub_metadata->get_type_of_field($field_name)) {
                    $field_name = $sub_metadata->get_field_for_column(self::get_mapping_value($association_mapping, 'indexBy'));
                    // Not a property, maybe a column name?
                    if (null === $type_of_field = $sub_metadata->get_type_of_field($field_name)) {
                        // Maybe the column name is the association join column?
                        $association_mapping = $sub_metadata->get_association_mapping($field_name);
                        $index_property = $sub_metadata->get_single_association_referenced_join_column_name($field_name);
                        $sub_metadata = $this->entity_manager->get_class_metadata(self::get_mapping_value($association_mapping, 'targetEntity'));
                        // Not a property, maybe a column name?
                        if (null === $type_of_field = $sub_metadata->get_type_of_field($index_property)) {
                            $field_name = $sub_metadata->get_field_for_column($index_property);
                            $type_of_field = $sub_metadata->get_type_of_field($field_name);
                        }
                    }
                }
                if (!$collection_key_type = $this->get_type_identifier($type_of_field)) {
                    return null;
                }
            }
            return Type::collection(Type::object(Collection::class), Type::object($class), Type::builtin($collection_key_type));
        }
        if (isset($metadata->embedded_classes[$property])) {
            return Type::object(self::get_mapping_value($metadata->embedded_classes[$property], 'class'));
        }
        if (!$metadata->has_field($property)) {
            return null;
        }
        $type_of_field = $metadata->get_type_of_field($property);
        if (!$type_identifier = $this->get_type_identifier($type_of_field)) {
            return null;
        }
        $nullable = $metadata instanceof Class_Metadata && $metadata->is_nullable($property);
        if (Types::BIGINT === $type_of_field) {
            return $nullable ? Type::nullable(Type::union(Type::int(), Type::string())) : Type::union(Type::int(), Type::string());
        }
        $enum_type = null;
        if (null !== $enum_class = self::get_mapping_value($metadata->get_field_mapping($property), 'enumType') ?? null) {
            $enum_type = $nullable ? Type::nullable(Type::enum($enum_class)) : Type::enum($enum_class);
        }
        $builtin_type = $nullable ? Type::nullable(Type::builtin($type_identifier)) : Type::builtin($type_identifier);
        return match ($type_identifier) {
            Type_Identifier::OBJECT => match ($type_of_field) {
                Types::DATE_MUTABLE, Types::DATETIME_MUTABLE, Types::DATETIMETZ_MUTABLE, 'vardatetime', Types::TIME_MUTABLE => $nullable ? Type::nullable(Type::object(\DateTime::class)) : Type::object(\DateTime::class),
                Types::DATE_IMMUTABLE, Types::DATETIME_IMMUTABLE, Types::DATETIMETZ_IMMUTABLE, Types::TIME_IMMUTABLE => $nullable ? Type::nullable(Type::object(\DateTimeImmutable::class)) : Type::object(\DateTimeImmutable::class),
                Types::DATEINTERVAL => $nullable ? Type::nullable(Type::object(\DateInterval::class)) : Type::object(\DateInterval::class),
                default => $builtin_type,
            },
            Type_Identifier::ARRAY => match ($type_of_field) {
                'array', 'json_array' => $enum_type ? null : ($nullable ? Type::nullable(Type::array()) : Type::array()),
                Types::SIMPLE_ARRAY => $nullable ? Type::nullable(Type::list($enum_type ?? Type::string())) : Type::list($enum_type ?? Type::string()),
                default => $builtin_type,
            },
            Type_Identifier::INT, Type_Identifier::STRING => $enum_type ?: $builtin_type,
            default => $builtin_type,
        };
    }
    public function is_readable(string $class, string $property, array $context = []): ?bool
    {
        return null;
    }
    public function is_writable(string $class, string $property, array $context = []): ?bool
    {
        if (null === ($metadata = $this->get_metadata($class)) || Class_Metadata::GENERATOR_TYPE_NONE === $metadata->generator_type || !\in_array($property, $metadata->get_identifier_field_names(), true)) {
            return null;
        }
        return false;
    }
    private function get_metadata(string $class): ?Class_Metadata
    {
        try {
            return $this->entity_manager->get_class_metadata($class);
        } catch (Mapping_Exception|Orm_Mapping_Exception) {
            return null;
        }
    }
    /**
     * Determines whether an association is nullable.
     *
     * @param array<string, mixed>|AssociationMapping $associationMapping
     *
     * @see https://github.com/doctrine/doctrine2/blob/v2.5.4/lib/Doctrine/ORM/Tools/EntityGenerator.php#L1221-L1246
     */
    private function is_association_nullable(array|Association_Mapping $association_mapping): bool
    {
        if (self::get_mapping_value($association_mapping, 'id')) {
            return false;
        }
        if (!self::get_mapping_value($association_mapping, 'joinColumns')) {
            return true;
        }
        $join_columns = self::get_mapping_value($association_mapping, 'joinColumns');
        foreach ($join_columns as $join_column) {
            if (false === self::get_mapping_value($join_column, 'nullable')) {
                return false;
            }
        }
        return true;
    }
    /**
     * Gets the corresponding built-in PHP type.
     */
    private function get_type_identifier(string $doctrine_type): ?Type_Identifier
    {
        return match ($doctrine_type) {
            Types::SMALLINT, Types::INTEGER => Type_Identifier::INT,
            Types::FLOAT => Type_Identifier::FLOAT,
            Types::BIGINT, Types::STRING, Types::TEXT, Types::GUID, Types::DECIMAL => Type_Identifier::STRING,
            Types::BOOLEAN => Type_Identifier::BOOL,
            Types::BLOB, Types::BINARY => Type_Identifier::RESOURCE,
            Types::DATE_MUTABLE, Types::DATETIME_MUTABLE, Types::DATETIMETZ_MUTABLE, 'vardatetime', Types::TIME_MUTABLE, Types::DATE_IMMUTABLE, Types::DATETIME_IMMUTABLE, Types::DATETIMETZ_IMMUTABLE, Types::TIME_IMMUTABLE, Types::DATEINTERVAL => Type_Identifier::OBJECT,
            Types::SIMPLE_ARRAY => Type_Identifier::ARRAY,
            default => null,
        };
    }
    private static function get_mapping_value(array|Association_Mapping|Embedded_Class_Mapping|Field_Mapping|Join_Column_Mapping $mapping, string $key): mixed
    {
        if ($mapping instanceof Association_Mapping || $mapping instanceof Embedded_Class_Mapping || $mapping instanceof Field_Mapping || $mapping instanceof Join_Column_Mapping) {
            return $mapping->{$key} ?? null;
        }
        return $mapping[$key] ?? null;
    }
}