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
namespace Symfony\Bridge\Doctrine\Form;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping\Class_Metadata;
use Doctrine\ORM\Mapping\Class_Metadata_Info;
use Doctrine\ORM\Mapping\Field_Mapping;
use Doctrine\ORM\Mapping\Join_Column_Mapping;
use Doctrine\ORM\Mapping\Mapping_Exception as LegacyMappingException;
use Doctrine\Persistence\Manager_Registry;
use Doctrine\Persistence\Mapping\Mapping_Exception;
use Doctrine\Persistence\Proxy;
use Symfony\Bridge\Doctrine\Form\Type\Entity_Type;
use Symfony\Component\Form\Extension\Core\Type\Checkbox_Type;
use Symfony\Component\Form\Extension\Core\Type\Collection_Type;
use Symfony\Component\Form\Extension\Core\Type\Date_Interval_Type;
use Symfony\Component\Form\Extension\Core\Type\Date_Time_Type;
use Symfony\Component\Form\Extension\Core\Type\Date_Type;
use Symfony\Component\Form\Extension\Core\Type\Integer_Type;
use Symfony\Component\Form\Extension\Core\Type\Number_Type;
use Symfony\Component\Form\Extension\Core\Type\Textarea_Type;
use Symfony\Component\Form\Extension\Core\Type\Text_Type;
use Symfony\Component\Form\Extension\Core\Type\Time_Type;
use Symfony\Component\Form\Form_Type_Guesser_Interface;
use Symfony\Component\Form\Guess\Guess;
use Symfony\Component\Form\Guess\Type_Guess;
use Symfony\Component\Form\Guess\Value_Guess;
class Doctrine_Orm_Type_Guesser implements Form_Type_Guesser_Interface
{
    private array $cache = [];
    public function __construct(protected Manager_Registry $registry)
    {
    }
    public function guess_type(string $class, string $property): ?Type_Guess
    {
        if (!$ret = $this->get_metadata($class)) {
            return new Type_Guess(Text_Type::class, [], Guess::LOW_CONFIDENCE);
        }
        [$metadata, $name] = $ret;
        if ($metadata->has_association($property)) {
            $multiple = $metadata->is_collection_valued_association($property);
            $mapping = $metadata->get_association_mapping($property);
            return new Type_Guess(Entity_Type::class, ['em' => $name, 'class' => $mapping['targetEntity'], 'multiple' => $multiple], Guess::HIGH_CONFIDENCE);
        }
        return match ($metadata->get_type_of_field($property)) {
            Types::SIMPLE_ARRAY => new Type_Guess(Collection_Type::class, [], Guess::MEDIUM_CONFIDENCE),
            Types::BOOLEAN => new Type_Guess(Checkbox_Type::class, [], Guess::HIGH_CONFIDENCE),
            Types::DATETIME_MUTABLE, Types::DATETIMETZ_MUTABLE, 'vardatetime' => new Type_Guess(Date_Time_Type::class, [], Guess::HIGH_CONFIDENCE),
            Types::DATETIME_IMMUTABLE, Types::DATETIMETZ_IMMUTABLE => new Type_Guess(Date_Time_Type::class, ['input' => 'datetime_immutable'], Guess::HIGH_CONFIDENCE),
            Types::DATEINTERVAL => new Type_Guess(Date_Interval_Type::class, [], Guess::HIGH_CONFIDENCE),
            Types::DATE_MUTABLE => new Type_Guess(Date_Type::class, [], Guess::HIGH_CONFIDENCE),
            Types::DATE_IMMUTABLE => new Type_Guess(Date_Type::class, ['input' => 'datetime_immutable'], Guess::HIGH_CONFIDENCE),
            Types::TIME_MUTABLE => new Type_Guess(Time_Type::class, [], Guess::HIGH_CONFIDENCE),
            Types::TIME_IMMUTABLE => new Type_Guess(Time_Type::class, ['input' => 'datetime_immutable'], Guess::HIGH_CONFIDENCE),
            Types::DECIMAL => new Type_Guess(Number_Type::class, ['input' => 'string'], Guess::MEDIUM_CONFIDENCE),
            Types::FLOAT => new Type_Guess(Number_Type::class, [], Guess::MEDIUM_CONFIDENCE),
            Types::INTEGER, Types::BIGINT, Types::SMALLINT => new Type_Guess(Integer_Type::class, [], Guess::MEDIUM_CONFIDENCE),
            Types::STRING => new Type_Guess(Text_Type::class, [], Guess::MEDIUM_CONFIDENCE),
            Types::TEXT => new Type_Guess(Textarea_Type::class, [], Guess::MEDIUM_CONFIDENCE),
            default => new Type_Guess(Text_Type::class, [], Guess::LOW_CONFIDENCE),
        };
    }
    public function guess_required(string $class, string $property): ?Value_Guess
    {
        $class_metadatas = $this->get_metadata($class);
        if (!$class_metadatas) {
            return null;
        }
        /** @var ClassMetadataInfo $classMetadata */
        $class_metadata = $class_metadatas[0];
        // Check whether the field exists and is nullable or not
        if (isset($class_metadata->field_mappings[$property])) {
            if (!$class_metadata->is_nullable($property) && Types::BOOLEAN !== $class_metadata->get_type_of_field($property)) {
                return new Value_Guess(true, Guess::HIGH_CONFIDENCE);
            }
            return new Value_Guess(false, Guess::MEDIUM_CONFIDENCE);
        }
        // Check whether the association exists, is a to-one association and its
        // join column is nullable or not
        if ($class_metadata->is_association_with_single_join_column($property)) {
            $mapping = $class_metadata->get_association_mapping($property);
            if (null === self::get_mapping_value($mapping['joinColumns'][0], 'nullable')) {
                // The "nullable" option defaults to true, in that case the
                // field should not be required.
                return new Value_Guess(false, Guess::HIGH_CONFIDENCE);
            }
            return new Value_Guess(!self::get_mapping_value($mapping['joinColumns'][0], 'nullable'), Guess::HIGH_CONFIDENCE);
        }
        return null;
    }
    public function guess_max_length(string $class, string $property): ?Value_Guess
    {
        $ret = $this->get_metadata($class);
        if ($ret && isset($ret[0]->field_mappings[$property])) {
            $mapping = $ret[0]->get_field_mapping($property);
            $length = $mapping instanceof Field_Mapping ? $mapping->length : $mapping['length'] ?? null;
            if (null !== $length) {
                return new Value_Guess($length, Guess::HIGH_CONFIDENCE);
            }
            if (\in_array($ret[0]->get_type_of_field($property), [Types::DECIMAL, Types::FLOAT], true)) {
                return new Value_Guess(null, Guess::MEDIUM_CONFIDENCE);
            }
        }
        return null;
    }
    public function guess_pattern(string $class, string $property): ?Value_Guess
    {
        $ret = $this->get_metadata($class);
        if (!$ret) {
            return null;
        }
        if (!isset($ret[0]->field_mappings[$property])) {
            return null;
        }
        if (\in_array($ret[0]->get_type_of_field($property), [Types::DECIMAL, Types::FLOAT], true)) {
            return new Value_Guess(null, Guess::MEDIUM_CONFIDENCE);
        }
        return null;
    }
    /**
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return array{0:ClassMetadata<T>, 1:string}|null
     */
    protected function get_metadata(string $class): ?array
    {
        // normalize class name
        $class = self::get_real_class(ltrim($class, '\\'));
        if (\array_key_exists($class, $this->cache)) {
            return $this->cache[$class];
        }
        $this->cache[$class] = null;
        foreach ($this->registry->get_managers() as $name => $em) {
            try {
                return $this->cache[$class] = [$em->get_class_metadata($class), $name];
            } catch (Mapping_Exception) {
                // not an entity or mapped super class
            } catch (Legacy_Mapping_Exception) {
                // not an entity or mapped super class, using Doctrine ORM 2.2
            }
        }
        return null;
    }
    private static function get_real_class(string $class): string
    {
        if (false === $pos = strrpos($class, '\\' . Proxy::MARKER . '\\')) {
            return $class;
        }
        return substr($class, $pos + Proxy::MARKER_LENGTH + 2);
    }
    private static function get_mapping_value(array|Join_Column_Mapping $mapping, string $key): mixed
    {
        if ($mapping instanceof Join_Column_Mapping) {
            return $mapping->{$key} ?? null;
        }
        return $mapping[$key] ?? null;
    }
}