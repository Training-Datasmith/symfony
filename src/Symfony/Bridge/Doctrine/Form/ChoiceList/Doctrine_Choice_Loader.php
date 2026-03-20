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

use Doctrine\Persistence\Object_Manager;
use Symfony\Component\Form\Choice_List\Loader\Abstract_Choice_Loader;
use Symfony\Component\Form\Exception\LogicException;
/**
 * Loads choices using a Doctrine object manager.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class Doctrine_Choice_Loader extends Abstract_Choice_Loader
{
    /** @var class-string */
    private readonly string $class;
    /**
     * Creates a new choice loader.
     *
     * Optionally, an implementation of {@link EntityLoaderInterface} can be
     * passed which optimizes the object loading for one of the Doctrine
     * mapper implementations.
     *
     * @param string $class The class name of the loaded objects
     */
    public function __construct(private readonly Object_Manager $manager, string $class, private readonly ?Id_Reader $id_reader = null, private readonly ?Entity_Loader_Interface $object_loader = null)
    {
        if ($id_reader && !$id_reader->is_single_id()) {
            throw new \InvalidArgumentException(\sprintf('The "$idReader" argument of "%s" must be null when the query cannot be optimized because of composite id fields.', __METHOD__));
        }
        $this->class = $manager->get_class_metadata($class)->get_name();
    }
    protected function load_choices(): iterable
    {
        return $this->object_loader ? $this->object_loader->get_entities() : $this->manager->get_repository($this->class)->find_all();
    }
    protected function do_load_values_for_choices(array $choices): array
    {
        // Optimize performance for single-field identifiers. We already
        // know that the IDs are used as values
        // Attention: This optimization does not check choices for existence
        if ($this->id_reader) {
            throw new LogicException('Not defining the IdReader explicitly as a value callback when the query can be optimized is not supported.');
        }
        return parent::do_load_values_for_choices($choices);
    }
    protected function do_load_choices_for_values(array $values, ?callable $value): array
    {
        if ($this->id_reader && null === $value) {
            throw new LogicException('Not defining the IdReader explicitly as a value callback when the query can be optimized is not supported.');
        }
        $id_reader = null;
        if (\is_array($value) && $value[0] instanceof Id_Reader) {
            $id_reader = $value[0];
        } elseif ($value instanceof \Closure) {
            $ref = new \ReflectionFunction($value);
            if (($r_this = $ref->get_closure_this()) instanceof Id_Reader) {
                $id_reader = $r_this;
            } elseif (($used_variables = $ref->get_closure_used_variables()) && ($used_variables['idReader'] ?? null) instanceof Id_Reader) {
                $id_reader = $used_variables['idReader'];
            }
        }
        // Optimize performance in case we have an object loader and
        // a single-field identifier
        if ($id_reader && $this->object_loader) {
            $objects = [];
            $objects_by_id = [];
            // Maintain order and indices from the given $values
            // An alternative approach to the following loop is to add the
            // "INDEX BY" clause to the Doctrine query in the loader,
            // but I'm not sure whether that's doable in a generic fashion.
            foreach ($this->object_loader->get_entities_by_ids($id_reader->get_id_field(), $values) as $object) {
                $objects_by_id[$value($object) ?? ''] = $object;
            }
            foreach ($values as $i => $id) {
                if (isset($objects_by_id[$id])) {
                    $objects[$i] = $objects_by_id[$id];
                }
            }
            return $objects;
        }
        return parent::do_load_choices_for_values($values, $value);
    }
}