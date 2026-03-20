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
namespace Symfony\Bridge\Doctrine\Form\Type;

use Doctrine\ORM\Query\Parameter;
use Doctrine\ORM\Query_Builder;
use Doctrine\Persistence\Object_Manager;
use Symfony\Bridge\Doctrine\Form\Choice_List\Orm_Query_Builder_Loader;
use Symfony\Component\Form\Exception\Unexpected_Type_Exception;
use Symfony\Component\Options_Resolver\Options;
use Symfony\Component\Options_Resolver\Options_Resolver;
class Entity_Type extends Doctrine_Type
{
    public function configure_options(Options_Resolver $resolver): void
    {
        parent::configure_options($resolver);
        // Invoke the query builder closure so that we can cache choice lists
        // for equal query builders
        $query_builder_normalizer = static function (Options $options, $query_builder) {
            if (\is_callable($query_builder)) {
                $query_builder = $query_builder($options['em']->get_repository($options['class']));
                if (null !== $query_builder && !$query_builder instanceof Query_Builder) {
                    throw new Unexpected_Type_Exception($query_builder, Query_Builder::class);
                }
            }
            return $query_builder;
        };
        $resolver->set_normalizer('query_builder', $query_builder_normalizer);
        $resolver->set_allowed_types('query_builder', ['null', 'callable', Query_Builder::class]);
    }
    /**
     * Return the default loader object.
     *
     * @param QueryBuilder $queryBuilder
     */
    public function get_loader(Object_Manager $manager, object $query_builder, string $class): Orm_Query_Builder_Loader
    {
        if (!$query_builder instanceof Query_Builder) {
            throw new \TypeError(\sprintf('Expected an instance of "%s", but got "%s".', Query_Builder::class, get_debug_type($query_builder)));
        }
        return new Orm_Query_Builder_Loader($query_builder);
    }
    public function get_block_prefix(): string
    {
        return 'entity';
    }
    /**
     * We consider two query builders with an equal SQL string and
     * equal parameters to be equal.
     *
     * @param QueryBuilder $queryBuilder
     *
     * @internal This method is public to be usable as callback. It should not
     *           be used in user code.
     */
    public function get_query_builder_parts_for_caching_hash(object $query_builder): ?array
    {
        if (!$query_builder instanceof Query_Builder) {
            throw new \TypeError(\sprintf('Expected an instance of "%s", but got "%s".', Query_Builder::class, get_debug_type($query_builder)));
        }
        return [$query_builder->get_query()->get_sql(), array_map($this->parameter_to_array(...), $query_builder->get_parameters()->to_array())];
    }
    /**
     * Converts a query parameter to an array.
     */
    private function parameter_to_array(Parameter $parameter): array
    {
        return [$parameter->get_name(), $parameter->get_type(), $parameter->get_value()];
    }
}