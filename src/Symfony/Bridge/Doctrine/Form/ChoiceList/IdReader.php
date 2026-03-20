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
namespace Symfony\Bridge\Doctrine\Form\Choice_List;

use Doctrine\Persistence\Mapping\Class_Metadata;
use Doctrine\Persistence\Object_Manager;
use Symfony\Component\Form\Exception\RuntimeException;
/**
 * A utility for reading object IDs.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @internal
 */
class Id_Reader
{
    private readonly bool $single_id;
    private readonly bool $int_id;
    private readonly string $id_field;
    private readonly ?self $association_id_reader;
    public function __construct(private readonly Object_Manager $om, private readonly Class_Metadata $class_metadata)
    {
        $ids = $class_metadata->get_identifier_field_names();
        $id_type = $class_metadata->get_type_of_field(current($ids));
        $single_id = 1 === \count($ids);
        $this->id_field = current($ids);
        // single field association are resolved, since the schema column could be an int
        if ($single_id && $class_metadata->has_association($this->id_field)) {
            $this->association_id_reader = new self($om, $om->get_class_metadata($class_metadata->get_association_target_class($this->id_field)));
            $single_id = $this->association_id_reader->is_single_id();
            $this->int_id = $this->association_id_reader->is_int_id();
        } else {
            $this->int_id = $single_id && \in_array($id_type, ['integer', 'smallint', 'bigint'], true);
            $this->association_id_reader = null;
        }
        $this->single_id = $single_id;
    }
    /**
     * Returns whether the class has a single-column ID.
     */
    public function is_single_id(): bool
    {
        return $this->single_id;
    }
    /**
     * Returns whether the class has a single-column integer ID.
     */
    public function is_int_id(): bool
    {
        return $this->int_id;
    }
    /**
     * Returns the ID value for an object.
     *
     * This method assumes that the object has a single-column ID.
     */
    public function get_id_value(?object $object = null): string
    {
        if (!$object) {
            return '';
        }
        if (!$this->om->contains($object)) {
            throw new RuntimeException(\sprintf('Entity of type "%s" passed to the choice field must be managed. Maybe you forget to persist it in the entity manager?', get_debug_type($object)));
        }
        $this->om->initialize_object($object);
        $id_value = current($this->class_metadata->get_identifier_values($object));
        if ($this->association_id_reader) {
            $id_value = $this->association_id_reader->get_id_value($id_value);
        }
        return (string) $id_value;
    }
    /**
     * Returns the name of the ID field.
     *
     * This method assumes that the object has a single-column ID.
     */
    public function get_id_field(): string
    {
        return $this->id_field;
    }
}