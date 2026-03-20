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

use Doctrine\Common\Collections\Collection;
use Doctrine\Persistence\Manager_Registry;
use Doctrine\Persistence\Object_Manager;
use Symfony\Bridge\Doctrine\Form\Choice_List\Doctrine_Choice_Loader;
use Symfony\Bridge\Doctrine\Form\Choice_List\Entity_Loader_Interface;
use Symfony\Bridge\Doctrine\Form\Choice_List\Id_Reader;
use Symfony\Bridge\Doctrine\Form\Data_Transformer\Collection_To_Array_Transformer;
use Symfony\Bridge\Doctrine\Form\Event_Listener\Merge_Doctrine_Collection_Listener;
use Symfony\Component\Form\Abstract_Type;
use Symfony\Component\Form\Choice_List\Choice_List;
use Symfony\Component\Form\Choice_List\Factory\Caching_Factory_Decorator;
use Symfony\Component\Form\Exception\RuntimeException;
use Symfony\Component\Form\Extension\Core\Type\Choice_Type;
use Symfony\Component\Form\Form_Builder_Interface;
use Symfony\Component\Options_Resolver\Options;
use Symfony\Component\Options_Resolver\Options_Resolver;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\Service\Reset_Interface;
abstract class Doctrine_Type extends Abstract_Type implements Reset_Interface
{
    /**
     * @var IdReader[]
     */
    private array $id_readers = [];
    /**
     * @var EntityLoaderInterface[]
     */
    private array $entity_loaders = [];
    /**
     * Creates the label for a choice.
     *
     * For backwards compatibility, objects are cast to strings by default.
     *
     * @internal This method is public to be usable as callback. It should not
     *           be used in user code.
     */
    public static function create_choice_label(object $choice): string
    {
        return (string) $choice;
    }
    /**
     * Creates the field name for a choice.
     *
     * This method is used to generate field names if the underlying object has
     * a single-column integer ID. In that case, the value of the field is
     * the ID of the object. That ID is also used as field name.
     *
     * @param string $value The choice value. Corresponds to the object's ID here.
     *
     * @internal This method is public to be usable as callback. It should not
     *           be used in user code.
     */
    public static function create_choice_name(object $choice, int|string $key, string $value): string
    {
        return str_replace('-', '_', $value);
    }
    /**
     * Gets important parts from QueryBuilder that will allow to cache its results.
     * For instance in ORM two query builders with an equal SQL string and
     * equal parameters are considered to be equal.
     *
     * @param object $queryBuilder A query builder, type declaration is not present here as there
     *                             is no common base class for the different implementations
     *
     * @internal This method is public to be usable as callback. It should not
     *           be used in user code.
     */
    public function get_query_builder_parts_for_caching_hash(object $query_builder): ?array
    {
        return null;
    }
    public function __construct(protected Manager_Registry $registry)
    {
    }
    public function build_form(Form_Builder_Interface $builder, array $options): void
    {
        if ($options['multiple'] && interface_exists(Collection::class)) {
            $builder->add_event_subscriber(new Merge_Doctrine_Collection_Listener())->add_view_transformer(new Collection_To_Array_Transformer(), true);
        }
    }
    public function configure_options(Options_Resolver $resolver): void
    {
        $choice_loader = function (Options $options): ?\Symfony\Component\Form\Choice_List\Factory\Cache\Choice_Loader {
            // Unless the choices are given explicitly, load them on demand
            if (null === $options['choices']) {
                // If there is no QueryBuilder we can safely cache
                $vary = [$options['em'], $options['class']];
                // also if concrete Type can return important QueryBuilder parts to generate
                // hash key we go for it as well, otherwise fallback on the instance
                if ($options['query_builder']) {
                    $vary[] = $this->get_query_builder_parts_for_caching_hash($options['query_builder']) ?? $options['query_builder'];
                }
                return Choice_List::loader($this, new Doctrine_Choice_Loader($options['em'], $options['class'], $options['id_reader'], $this->get_cached_entity_loader($options['em'], $options['query_builder'] ?? $options['em']->get_repository($options['class'])->create_query_builder('e'), $options['class'], $vary)), $vary);
            }
            return null;
        };
        $choice_name = function (Options $options): ?\Symfony\Component\Form\Choice_List\Factory\Cache\Choice_Field_Name {
            // If the object has a single-column, numeric ID, use that ID as
            // field name. We can only use numeric IDs as names, as we cannot
            // guarantee that a non-numeric ID contains a valid form name
            if ($options['id_reader'] instanceof Id_Reader && $options['id_reader']->is_int_id()) {
                return Choice_List::field_name($this, [self::class, 'createChoiceName']);
            }
            // Otherwise, an incrementing integer is used as name automatically
            return null;
        };
        // The choices are always indexed by ID (see "choices" normalizer
        // and DoctrineChoiceLoader), unless the ID is composite. Then they
        // are indexed by an incrementing integer.
        // Use the ID/incrementing integer as choice value.
        $choice_value = function (Options $options): ?\Symfony\Component\Form\Choice_List\Factory\Cache\Choice_Value {
            // If the entity has a single-column ID, use that ID as value
            if ($options['id_reader'] instanceof Id_Reader && $options['id_reader']->is_single_id()) {
                $id_reader = $options['id_reader'];
                $uid_format = $options['uid_format'];
                if (null === $uid_format) {
                    return Choice_List::value($this, $id_reader->get_id_value(...), $id_reader);
                }
                $format_method = match ($uid_format) {
                    'base32' => 'toBase32',
                    'base58' => 'toBase58',
                    'binary' => 'toBinary',
                    'hex' => 'toHex',
                    'rfc4122' => 'toRfc4122',
                    default => throw new RuntimeException(\sprintf('Unsupported value "%s" for "uid_format" option; valid values are "base32", "base58", "binary", "hex" and "rfc4122".', $uid_format)),
                };
                return Choice_List::value($this, static function (?object $object = null) use ($id_reader, $format_method): string {
                    if ('' === $value = $id_reader->get_id_value($object)) {
                        return '';
                    }
                    return Uuid::from_string($value)->{$format_method}();
                }, [$id_reader, $uid_format]);
            }
            // Otherwise, an incrementing integer is used as value automatically
            return null;
        };
        $em_normalizer = function (Options $options, $em) {
            if (null !== $em) {
                if ($em instanceof Object_Manager) {
                    return $em;
                }
                return $this->registry->get_manager($em);
            }
            $em = $this->registry->get_manager_for_class($options['class']);
            if (null === $em) {
                throw new RuntimeException(\sprintf('Class "%s" seems not to be a managed Doctrine entity. Did you forget to map it?', $options['class']));
            }
            return $em;
        };
        // Invoke the query builder closure so that we can cache choice lists
        // for equal query builders
        $query_builder_normalizer = static function (Options $options, $query_builder) {
            if (\is_callable($query_builder)) {
                return $query_builder($options['em']->get_repository($options['class']));
            }
            return $query_builder;
        };
        // Set the "id_reader" option via the normalizer. This option is not
        // supposed to be set by the user.
        // The ID reader is a utility that is needed to read the object IDs
        // when generating the field values. The callback generating the
        // field values has no access to the object manager or the class
        // of the field, so we store that information in the reader.
        // The reader is cached so that two choice lists for the same class
        // (and hence with the same reader) can successfully be cached.
        $id_reader_normalizer = fn(Options $options): ?\Symfony\Bridge\Doctrine\Form\Choice_List\Id_Reader => $this->get_cached_id_reader($options['em'], $options['class']);
        $resolver->set_defaults([
            'em' => null,
            'query_builder' => null,
            'choices' => null,
            'choice_loader' => $choice_loader,
            'choice_label' => Choice_List::label($this, [self::class, 'createChoiceLabel']),
            'choice_name' => $choice_name,
            'choice_value' => $choice_value,
            'id_reader' => null,
            // internal
            'choice_translation_domain' => false,
            'uid_format' => null,
        ]);
        $resolver->set_required(['class']);
        $resolver->set_normalizer('em', $em_normalizer);
        $resolver->set_normalizer('query_builder', $query_builder_normalizer);
        $resolver->set_normalizer('id_reader', $id_reader_normalizer);
        $resolver->set_allowed_types('em', ['null', 'string', Object_Manager::class]);
        $resolver->set_allowed_values('uid_format', [null, 'base32', 'base58', 'binary', 'hex', 'rfc4122']);
    }
    /**
     * Return the default loader object.
     */
    abstract public function get_loader(Object_Manager $manager, object $query_builder, string $class): Entity_Loader_Interface;
    public function get_parent(): string
    {
        return Choice_Type::class;
    }
    public function reset(): void
    {
        $this->id_readers = [];
        $this->entity_loaders = [];
    }
    private function get_cached_id_reader(Object_Manager $manager, string $class): ?Id_Reader
    {
        $hash = Caching_Factory_Decorator::generate_hash([$manager, $class]);
        if (isset($this->id_readers[$hash])) {
            return $this->id_readers[$hash];
        }
        $id_reader = new Id_Reader($manager, $manager->get_class_metadata($class));
        // don't cache the instance for composite ids that cannot be optimized
        return $this->id_readers[$hash] = $id_reader->is_single_id() ? $id_reader : null;
    }
    private function get_cached_entity_loader(Object_Manager $manager, object $query_builder, string $class, array $vary): Entity_Loader_Interface
    {
        $hash = Caching_Factory_Decorator::generate_hash($vary);
        return $this->entity_loaders[$hash] ??= $this->get_loader($manager, $query_builder, $class);
    }
}