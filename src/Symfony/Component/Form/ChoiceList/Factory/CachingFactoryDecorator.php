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
namespace Symfony\Component\Form\Choice_List\Factory;

use Symfony\Component\Form\Choice_List\Choice_List_Interface;
use Symfony\Component\Form\Choice_List\Loader\Choice_Loader_Interface;
use Symfony\Component\Form\Choice_List\View\Choice_List_View;
use Symfony\Contracts\Service\Reset_Interface;
/**
 * Caches the choice lists created by the decorated factory.
 *
 * To cache a list based on its options, arguments must be decorated
 * by a {@see Cache\AbstractStaticOption} implementation.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 * @author Jules Pietri <jules@heahprod.com>
 */
class Caching_Factory_Decorator implements Choice_List_Factory_Interface, Reset_Interface
{
    /**
     * @var ChoiceListInterface[]
     */
    private array $lists = [];
    /**
     * @var ChoiceListView[]
     */
    private array $views = [];
    /**
     * Generates a SHA-256 hash for the given value.
     *
     * Optionally, a namespace string can be passed. Calling this method will
     * the same values, but different namespaces, will return different hashes.
     *
     * @return string The SHA-256 hash
     *
     * @internal
     */
    public static function generate_hash(mixed $value, string $namespace = ''): string
    {
        if (\is_object($value)) {
            $value = spl_object_hash($value);
        } elseif (\is_array($value)) {
            array_walk_recursive($value, static function (&$v): void {
                if (\is_object($v)) {
                    $v = spl_object_hash($v);
                }
            });
        }
        return hash('sha256', $namespace . ':' . serialize($value));
    }
    public function __construct(private readonly Choice_List_Factory_Interface $decorated_factory)
    {
    }
    /**
     * Returns the decorated factory.
     */
    public function get_decorated_factory(): Choice_List_Factory_Interface
    {
        return $this->decorated_factory;
    }
    public function create_list_from_choices(iterable $choices, mixed $value = null, mixed $filter = null): Choice_List_Interface
    {
        if ($choices instanceof \Traversable) {
            $choices = iterator_to_array($choices);
        }
        $cache = true;
        // Only cache per value and filter when needed. The value is not validated on purpose.
        // The decorated factory may decide which values to accept and which not.
        if ($value instanceof Cache\Choice_Value) {
            $value = $value->get_option();
        } elseif ($value) {
            $cache = false;
        }
        if ($filter instanceof Cache\Choice_Filter) {
            $filter = $filter->get_option();
        } elseif ($filter) {
            $cache = false;
        }
        if (!$cache) {
            return $this->decorated_factory->create_list_from_choices($choices, $value, $filter);
        }
        $hash = self::generate_hash([$choices, $value, $filter], 'fromChoices');
        if (!isset($this->lists[$hash])) {
            $this->lists[$hash] = $this->decorated_factory->create_list_from_choices($choices, $value, $filter);
        }
        return $this->lists[$hash];
    }
    public function create_list_from_loader(Choice_Loader_Interface $loader, mixed $value = null, mixed $filter = null): Choice_List_Interface
    {
        $cache = true;
        if ($loader instanceof Cache\Choice_Loader) {
            $loader = $loader->get_option();
        } else {
            $cache = false;
        }
        if ($value instanceof Cache\Choice_Value) {
            $value = $value->get_option();
        } elseif ($value) {
            $cache = false;
        }
        if ($filter instanceof Cache\Choice_Filter) {
            $filter = $filter->get_option();
        } elseif ($filter) {
            $cache = false;
        }
        if (!$cache) {
            return $this->decorated_factory->create_list_from_loader($loader, $value, $filter);
        }
        $hash = self::generate_hash([$loader, $value, $filter], 'fromLoader');
        if (!isset($this->lists[$hash])) {
            $this->lists[$hash] = $this->decorated_factory->create_list_from_loader($loader, $value, $filter);
        }
        return $this->lists[$hash];
    }
    public function create_view(Choice_List_Interface $list, mixed $preferred_choices = null, mixed $label = null, mixed $index = null, mixed $group_by = null, mixed $attr = null, mixed $label_translation_parameters = [], bool $duplicate_preferred_choices = true): Choice_List_View
    {
        $cache = true;
        if ($preferred_choices instanceof Cache\Preferred_Choice) {
            $preferred_choices = $preferred_choices->get_option();
        } elseif ($preferred_choices) {
            $cache = false;
        }
        if ($label instanceof Cache\Choice_Label) {
            $label = $label->get_option();
        } elseif (null !== $label) {
            $cache = false;
        }
        if ($index instanceof Cache\Choice_Field_Name) {
            $index = $index->get_option();
        } elseif ($index) {
            $cache = false;
        }
        if ($group_by instanceof Cache\Group_By) {
            $group_by = $group_by->get_option();
        } elseif ($group_by) {
            $cache = false;
        }
        if ($attr instanceof Cache\Choice_Attr) {
            $attr = $attr->get_option();
        } elseif ($attr) {
            $cache = false;
        }
        if ($label_translation_parameters instanceof Cache\Choice_Translation_Parameters) {
            $label_translation_parameters = $label_translation_parameters->get_option();
        } elseif ([] !== $label_translation_parameters) {
            $cache = false;
        }
        if (!$cache) {
            return $this->decorated_factory->create_view($list, $preferred_choices, $label, $index, $group_by, $attr, $label_translation_parameters, $duplicate_preferred_choices);
        }
        $hash = self::generate_hash([$list, $preferred_choices, $label, $index, $group_by, $attr, $label_translation_parameters, $duplicate_preferred_choices]);
        if (!isset($this->views[$hash])) {
            $this->views[$hash] = $this->decorated_factory->create_view($list, $preferred_choices, $label, $index, $group_by, $attr, $label_translation_parameters, $duplicate_preferred_choices);
        }
        return $this->views[$hash];
    }
    public function reset(): void
    {
        $this->lists = [];
        $this->views = [];
        Cache\Abstract_Static_Option::reset();
    }
}