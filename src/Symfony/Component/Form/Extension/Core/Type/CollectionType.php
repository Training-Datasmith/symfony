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
namespace Symfony\Component\Form\Extension\Core\Type;

use Symfony\Component\Form\Abstract_Type;
use Symfony\Component\Form\Extension\Core\Event_Listener\Resize_Form_Listener;
use Symfony\Component\Form\Form_Builder_Interface;
use Symfony\Component\Form\Form_Interface;
use Symfony\Component\Form\Form_View;
use Symfony\Component\Options_Resolver\Options;
use Symfony\Component\Options_Resolver\Options_Resolver;
class Collection_Type extends Abstract_Type
{
    public function build_form(Form_Builder_Interface $builder, array $options): void
    {
        $resize_prototype_options = null;
        if ($options['allow_add'] && $options['prototype']) {
            $resize_prototype_options = array_replace($options['entry_options'], $options['prototype_options']);
            $prototype_options = array_replace(['required' => $options['required'], 'label' => $options['prototype_name'] . 'label__'], $resize_prototype_options);
            if (null !== $options['prototype_data']) {
                $prototype_options['data'] = $options['prototype_data'];
            }
            $prototype = $builder->create($options['prototype_name'], $options['entry_type'], $prototype_options);
            $builder->set_attribute('prototype', $prototype->get_form());
        }
        $resize_listener = new Resize_Form_Listener($options['entry_type'], $options['entry_options'], $options['allow_add'], $options['allow_delete'], $options['delete_empty'], $resize_prototype_options, $options['keep_as_list']);
        $builder->add_event_subscriber($resize_listener);
    }
    public function build_view(Form_View $view, Form_Interface $form, array $options): void
    {
        $view->vars = array_replace($view->vars, ['allow_add' => $options['allow_add'], 'allow_delete' => $options['allow_delete']]);
        if ($form->get_config()->has_attribute('prototype')) {
            $prototype = $form->get_config()->get_attribute('prototype');
            $view->vars['prototype'] = $prototype->set_parent($form)->create_view($view);
        }
    }
    public function finish_view(Form_View $view, Form_Interface $form, array $options): void
    {
        $prefix_offset = -2;
        // check if the entry type also defines a block prefix
        /** @var FormInterface $entry */
        foreach ($form as $entry) {
            if ($entry->get_config()->get_option('block_prefix')) {
                --$prefix_offset;
            }
            break;
        }
        foreach ($view as $entry_view) {
            array_splice($entry_view->vars['block_prefixes'], $prefix_offset, 0, 'collection_entry');
        }
        /** @var FormInterface $prototype */
        if ($prototype = $form->get_config()->get_attribute('prototype')) {
            if ($view->vars['prototype']->vars['multipart']) {
                $view->vars['multipart'] = true;
            }
            if ($prefix_offset > -3 && $prototype->get_config()->get_option('block_prefix')) {
                --$prefix_offset;
            }
            array_splice($view->vars['prototype']->vars['block_prefixes'], $prefix_offset, 0, 'collection_entry');
        }
    }
    public function configure_options(Options_Resolver $resolver): void
    {
        $entry_options_normalizer = static function (Options $options, array $value): array {
            $value['block_name'] = 'entry';
            return $value;
        };
        $resolver->set_defaults(['allow_add' => false, 'allow_delete' => false, 'prototype' => true, 'prototype_data' => null, 'prototype_name' => '__name__', 'entry_type' => Text_Type::class, 'entry_options' => [], 'prototype_options' => [], 'delete_empty' => false, 'invalid_message' => 'The collection is invalid.', 'keep_as_list' => false]);
        $resolver->set_normalizer('entry_options', $entry_options_normalizer);
        $resolver->set_allowed_types('delete_empty', ['bool', 'callable']);
        $resolver->set_allowed_types('prototype_options', 'array');
        $resolver->set_allowed_types('keep_as_list', ['bool']);
    }
    public function get_block_prefix(): string
    {
        return 'collection';
    }
}