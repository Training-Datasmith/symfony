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

use Doctrine\DBAL\Array_Parameter_Type;
use Doctrine\DBAL\Types\Conversion_Exception;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\Query_Builder;
use Symfony\Bridge\Doctrine\Types\Abstract_Uid_Type;
use Symfony\Component\Form\Exception\Transformation_Failed_Exception;
/**
 * Loads entities using a {@link QueryBuilder} instance.
 *
 * @author Benjamin Eberlei <kontakt@beberlei.de>
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class Orm_Query_Builder_Loader implements Entity_Loader_Interface
{
    public function __construct(private readonly Query_Builder $query_builder)
    {
    }
    public function get_entities(): array
    {
        return $this->query_builder->get_query()->execute();
    }
    public function get_entities_by_ids(string $identifier, array $values): array
    {
        if (null !== $this->query_builder->get_max_results() || 0 < (int) $this->query_builder->get_first_result()) {
            // an offset or a limit would apply on results including the where clause with submitted id values
            // that could make invalid choices valid
            $choices = [];
            $metadata = $this->query_builder->get_entity_manager()->get_class_metadata(current($this->query_builder->get_root_entities()));
            foreach ($this->get_entities() as $entity) {
                if (\in_array((string) current($metadata->get_identifier_values($entity)), $values, true)) {
                    $choices[] = $entity;
                }
            }
            return $choices;
        }
        $qb = clone $this->query_builder;
        $alias = current($qb->get_root_aliases());
        $parameter = 'ORMQueryBuilderLoader_getEntitiesByIds_' . $identifier;
        $parameter = str_replace('.', '_', $parameter);
        $where = $qb->expr()->in($alias . '.' . $identifier, ':' . $parameter);
        // Guess type
        $entity = current($qb->get_root_entities());
        $metadata = $qb->get_entity_manager()->get_class_metadata($entity);
        if (\in_array($type = $metadata->get_type_of_field($identifier), ['integer', 'bigint', 'smallint'], true)) {
            $parameter_type = Array_Parameter_Type::INTEGER;
            // Filter out non-integer values (e.g. ""). If we don't, some
            // databases such as PostgreSQL fail.
            $values = array_values(array_filter($values, static fn($v): bool => \is_string($v) && ctype_digit($v) || (string) $v === (string) (int) $v));
        } elseif (null !== $type && (\in_array($type, ['ulid', 'uuid', 'guid'], true) || Type::has_type($type) && is_subclass_of(Type::get_type($type), Abstract_Uid_Type::class))) {
            $parameter_type = Array_Parameter_Type::STRING;
            // Like above, but we just filter out empty strings.
            $values = array_values(array_filter($values, static fn($v): bool => '' !== (string) $v));
            // Convert values into right type
            if (Type::has_type($type)) {
                $doctrine_type = Type::get_type($type);
                $platform = $qb->get_entity_manager()->get_connection()->get_database_platform();
                foreach ($values as &$value) {
                    try {
                        $value = $doctrine_type->convert_to_database_value($value, $platform);
                    } catch (Conversion_Exception $e) {
                        throw new Transformation_Failed_Exception(\sprintf('Failed to transform "%s" into "%s".', $value, $type), 0, $e);
                    }
                }
                unset($value);
            }
        } else {
            $parameter_type = Array_Parameter_Type::STRING;
        }
        if (!$values) {
            return [];
        }
        return $qb->and_where($where)->get_query()->set_parameter($parameter, $values, $parameter_type)->get_result();
    }
}