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
namespace Symfony\Bridge\Doctrine\Validator\Constraints;

use Doctrine\ORM\Mapping\Mapping_Exception as ORMMappingException;
use Doctrine\Persistence\Manager_Registry;
use Doctrine\Persistence\Mapping\Class_Metadata;
use Doctrine\Persistence\Mapping\Mapping_Exception as PersistenceMappingException;
use Doctrine\Persistence\Object_Manager;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraint_Validator;
use Symfony\Component\Validator\Exception\Constraint_Definition_Exception;
use Symfony\Component\Validator\Exception\Unexpected_Type_Exception;
use Symfony\Component\Validator\Exception\UnexpectedValueException;
/**
 * Unique Entity Validator checks if one or a set of fields contain unique values.
 *
 * @author Benjamin Eberlei <kontakt@beberlei.de>
 */
class Unique_Entity_Validator extends Constraint_Validator
{
    public function __construct(private readonly Manager_Registry $registry)
    {
    }
    /**
     * @throws UnexpectedTypeException
     * @throws ConstraintDefinitionException
     */
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof Unique_Entity) {
            throw new Unexpected_Type_Exception($constraint, Unique_Entity::class);
        }
        if (!\is_array($constraint->fields) && !\is_string($constraint->fields)) {
            throw new Unexpected_Type_Exception($constraint->fields, 'array');
        }
        if (null !== $constraint->error_path && !\is_string($constraint->error_path)) {
            throw new Unexpected_Type_Exception($constraint->error_path, 'string or null');
        }
        $fields = (array) $constraint->fields;
        if (0 === \count($fields)) {
            throw new Constraint_Definition_Exception('At least one field has to be specified.');
        }
        if (null === $value) {
            return;
        }
        if (!\is_object($value)) {
            throw new UnexpectedValueException($value, 'object');
        }
        $entity_class = $constraint->entity_class ?? $value::class;
        if ($constraint->em) {
            try {
                $em = $this->registry->get_manager($constraint->em);
            } catch (\InvalidArgumentException $e) {
                throw new Constraint_Definition_Exception(\sprintf('Object manager "%s" does not exist.', $constraint->em), 0, $e);
            }
        } else {
            $em = $this->registry->get_manager_for_class($entity_class);
            if (!$em) {
                throw new Constraint_Definition_Exception(\sprintf('Unable to find the object manager associated with an entity of class "%s".', $entity_class));
            }
        }
        try {
            $em->get_repository($value::class);
            $is_value_entity = true;
        } catch (Orm_Mapping_Exception|Persistence_Mapping_Exception) {
            $is_value_entity = false;
        }
        $class = $em->get_class_metadata($entity_class);
        $criteria = [];
        $has_ignorable_null_value = false;
        $field_values = $this->get_field_values($value, $class, $fields, $is_value_entity);
        foreach ($field_values as $field_name => $field_value) {
            if (null === $field_value && $this->ignore_null_for_field($constraint, $field_name)) {
                $has_ignorable_null_value = true;
                continue;
            }
            $criteria[$field_name] = $field_value;
            if (\is_object($criteria[$field_name]) && $class->has_association($field_name)) {
                /* Ensure the Proxy is initialized before using reflection to
                 * read its identifiers. This is necessary because the wrapped
                 * getter methods in the Proxy are being bypassed.
                 */
                $em->initialize_object($criteria[$field_name]);
            }
        }
        // validation doesn't fail if one of the fields is null and if null values should be ignored
        if ($has_ignorable_null_value) {
            return;
        }
        // skip validation if there are no criteria (this can happen when the
        // "ignoreNull" option is enabled and fields to be checked are null
        if (!$criteria) {
            return;
        }
        if (null !== $constraint->entity_class) {
            /* Retrieve repository from given entity name.
             * We ensure the retrieved repository can handle the entity
             * by checking the entity is the same, or subclass of the supported entity.
             */
            $repository = $em->get_repository($constraint->entity_class);
            $supported_class = $repository->get_class_name();
            if ($is_value_entity && !$value instanceof $supported_class) {
                $class = $em->get_class_metadata($value::class);
                throw new Constraint_Definition_Exception(\sprintf('The "%s" entity repository does not support the "%s" entity. The entity should be an instance of or extend "%s".', $constraint->entity_class, $class->get_name(), $supported_class));
            }
        } else {
            $repository = $em->get_repository($value::class);
        }
        $arguments = [$criteria];
        /* If the default repository method is used, it is always enough to retrieve at most two entities because:
         * - No entity returned, the current entity is definitely unique.
         * - More than one entity returned, the current entity cannot be unique.
         * - One entity returned the uniqueness depends on the current entity.
         */
        if ('findBy' === $constraint->repository_method) {
            $arguments = [$criteria, null, 2];
        }
        $result = $repository->{$constraint->repository_method}(...$arguments);
        if ($result instanceof \IteratorAggregate) {
            $result = $result->getIterator();
        }
        /* If the result is a MongoCursor, it must be advanced to the first
         * element. Rewinding should have no ill effect if $result is another
         * iterator implementation.
         */
        if ($result instanceof \Iterator) {
            $result->rewind();
            if ($result instanceof \Countable && 1 < \count($result)) {
                $result = [$result->current(), $result->current()];
            } else {
                $result = $result->valid() && null !== $result->current() ? [$result->current()] : [];
            }
        } elseif (\is_array($result)) {
            reset($result);
        } else {
            $result = null === $result ? [] : [$result];
        }
        /* If no entity matched the query criteria or a single entity matched,
         * which is the same as the entity being validated, the criteria is
         * unique.
         */
        if (!$result || 1 === \count($result) && current($result) === $value) {
            return;
        }
        /* If a single entity matched the query criteria, which is the same as
         * the entity being updated by validated object, the criteria is unique.
         */
        if (!$is_value_entity && !empty($constraint->identifier_field_names) && 1 === \count($result)) {
            $field_values = $this->get_field_values($value, $class, $constraint->identifier_field_names);
            if (array_values($class->get_identifier_field_names()) != array_values($constraint->identifier_field_names)) {
                throw new Constraint_Definition_Exception(\sprintf('The "%s" entity identifier field names should be "%s", not "%s".', $entity_class, implode(', ', $class->get_identifier_field_names()), implode(', ', $constraint->identifier_field_names)));
            }
            $entity_matched = true;
            foreach ($constraint->identifier_field_names as $identifier_field_name) {
                $property_value = $this->get_property_value($entity_class, $identifier_field_name, current($result));
                if ($field_values[$identifier_field_name] instanceof \Stringable) {
                    $field_values[$identifier_field_name] = (string) $field_values[$identifier_field_name];
                }
                if ($property_value instanceof \Stringable) {
                    $property_value = (string) $property_value;
                }
                if ($field_values[$identifier_field_name] !== $property_value) {
                    $entity_matched = false;
                    break;
                }
            }
            if ($entity_matched) {
                return;
            }
        }
        $error_path = $constraint->error_path ?? current($fields);
        $invalid_value = $criteria[$error_path] ?? $criteria[current($fields)];
        $this->context->build_violation($constraint->message)->at_path($error_path)->set_parameter('{{ value }}', $this->format_with_identifiers($em, $class, $invalid_value))->set_invalid_value($invalid_value)->set_code(Unique_Entity::NOT_UNIQUE_ERROR)->set_cause($result)->add_violation();
    }
    private function ignore_null_for_field(Unique_Entity $constraint, string $field_name): bool
    {
        if (\is_bool($constraint->ignore_null)) {
            return $constraint->ignore_null;
        }
        return \in_array($field_name, (array) $constraint->ignore_null, true);
    }
    private function format_with_identifiers(Object_Manager $em, Class_Metadata $class, mixed $value): string
    {
        if (!\is_object($value) || $value instanceof \DateTimeInterface) {
            return $this->format_value($value, self::PRETTY_DATE);
        }
        if ($value instanceof \Stringable) {
            return (string) $value;
        }
        if ($class->get_name() !== $id_class = $value::class) {
            // non-unique value might be a composite PK that consists of other entity objects
            if ($em->get_metadata_factory()->has_metadata_for($id_class)) {
                $identifiers = $em->get_class_metadata($id_class)->get_identifier_values($value);
            } else {
                // this case might happen if the non-unique column has a custom doctrine type and its value is an object
                // in which case we cannot get any identifiers for it
                $identifiers = [];
            }
        } else {
            $identifiers = $class->get_identifier_values($value);
        }
        if (!$identifiers) {
            return \sprintf('object("%s")', $id_class);
        }
        array_walk($identifiers, function (&$id, $field): void {
            if (!\is_object($id) || $id instanceof \DateTimeInterface) {
                $id_as_string = $this->format_value($id, self::PRETTY_DATE);
            } else {
                $id_as_string = \sprintf('object("%s")', $id::class);
            }
            $id = \sprintf('%s => %s', $field, $id_as_string);
        });
        return \sprintf('object("%s") identified by (%s)', $id_class, implode(', ', $identifiers));
    }
    private function get_field_values(mixed $object, Class_Metadata $class, array $fields, bool $is_value_entity = false): array
    {
        if (!$is_value_entity) {
            $reflection_object = new \Reflection_Object($object);
        }
        $field_values = [];
        $object_class = $object::class;
        foreach ($fields as $object_field_name => $entity_field_name) {
            if (!$class->has_field($entity_field_name) && !$class->has_association($entity_field_name)) {
                throw new Constraint_Definition_Exception(\sprintf('The field "%s" is not mapped by Doctrine, so it cannot be validated for uniqueness.', $entity_field_name));
            }
            $field_name = \is_int($object_field_name) ? $entity_field_name : $object_field_name;
            if (!$is_value_entity && !$reflection_object->has_property($field_name)) {
                throw new Constraint_Definition_Exception(\sprintf('The field "%s" is not a property of class "%s".', $field_name, $object_class));
            }
            if ($is_value_entity && $object instanceof ($class->get_name()) && property_exists($class, 'propertyAccessors')) {
                $field_values[$entity_field_name] = $class->property_accessors[$field_name]->get_value($object);
            } elseif ($is_value_entity && $object instanceof ($class->get_name())) {
                $field_values[$entity_field_name] = $class->refl_fields[$field_name]->get_value($object);
            } else {
                $field_values[$entity_field_name] = $this->get_property_value($object_class, $field_name, $object);
            }
        }
        return $field_values;
    }
    private function get_property_value(string $class, string $name, mixed $object): mixed
    {
        $property = new \ReflectionProperty($class, $name);
        return $property->get_value($object);
    }
}