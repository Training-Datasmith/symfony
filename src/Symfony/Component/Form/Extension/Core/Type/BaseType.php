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

use Symfony\Component\Form\Abstract_Renderer_Engine;
use Symfony\Component\Form\Abstract_Type;
use Symfony\Component\Form\Exception\LogicException;
use Symfony\Component\Form\Form_Builder_Interface;
use Symfony\Component\Form\Form_Interface;
use Symfony\Component\Form\Form_View;
use Symfony\Component\Options_Resolver\Options_Resolver;
/**
 * Encapsulates common logic of {@link FormType} and {@link ButtonType}.
 *
 * This type does not appear in the form's type inheritance chain and as such
 * cannot be extended (via {@link \Symfony\Component\Form\FormExtensionInterface}) nor themed.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
abstract class Base_Type extends Abstract_Type
{
    public function build_form(Form_Builder_Interface $builder, array $options): void
    {
        $builder->set_disabled($options['disabled']);
        $builder->set_auto_initialize($options['auto_initialize']);
    }
    public function build_view(Form_View $view, Form_Interface $form, array $options): void
    {
        $name = $form->get_name();
        $block_name = $options['block_name'] ?: $form->get_name();
        $translation_domain = $options['translation_domain'];
        $label_translation_parameters = $options['label_translation_parameters'];
        $attr_translation_parameters = $options['attr_translation_parameters'];
        $label_format = $options['label_format'];
        if ($view->parent) {
            if ('' !== $parent_full_name = $view->parent->vars['full_name']) {
                $id = \sprintf('%s_%s', $view->parent->vars['id'], $name);
                $full_name = \sprintf('%s[%s]', $parent_full_name, $name);
                $unique_block_prefix = \sprintf('%s_%s', $view->parent->vars['unique_block_prefix'], $block_name);
            } else {
                $id = $name;
                $full_name = $name;
                $unique_block_prefix = '_' . $block_name;
            }
            $translation_domain ??= $view->parent->vars['translation_domain'];
            $label_translation_parameters = array_merge($view->parent->vars['label_translation_parameters'], $label_translation_parameters);
            $attr_translation_parameters = array_merge($view->parent->vars['attr_translation_parameters'], $attr_translation_parameters);
            if (!$label_format) {
                $label_format = $view->parent->vars['label_format'];
            }
            $root_form_attr_option = $form->get_root()->get_config()->get_option('form_attr');
            if ($options['form_attr'] || $root_form_attr_option) {
                $options['attr']['form'] = \is_string($root_form_attr_option) ? $root_form_attr_option : $form->get_root()->get_name();
                if (empty($options['attr']['form'])) {
                    throw new LogicException('"form_attr" option must be a string identifier on root form when it has no id.');
                }
            }
        } else {
            $id = \is_string($options['form_attr']) ? $options['form_attr'] : $name;
            $full_name = $name;
            $unique_block_prefix = '_' . $block_name;
            // Strip leading underscores and digits. These are allowed in
            // form names, but not in HTML4 ID attributes.
            // https://www.w3.org/TR/html401/struct/global#adef-id
            $id = ltrim($id, '_0123456789');
        }
        $block_prefixes = [];
        for ($type = $form->get_config()->get_type(); null !== $type; $type = $type->get_parent()) {
            array_unshift($block_prefixes, $type->get_block_prefix());
        }
        if (null !== $options['block_prefix']) {
            $block_prefixes[] = $options['block_prefix'];
        }
        $block_prefixes[] = $unique_block_prefix;
        $view->vars = array_replace($view->vars, [
            'form' => $view,
            'id' => $id,
            'name' => $name,
            'full_name' => $full_name,
            'disabled' => $form->is_disabled(),
            'label' => $options['label'],
            'label_format' => $label_format,
            'label_html' => $options['label_html'],
            'multipart' => false,
            'attr' => $options['attr'],
            'block_prefixes' => $block_prefixes,
            'unique_block_prefix' => $unique_block_prefix,
            'row_attr' => $options['row_attr'],
            'translation_domain' => $translation_domain,
            'label_translation_parameters' => $label_translation_parameters,
            'attr_translation_parameters' => $attr_translation_parameters,
            'priority' => $options['priority'],
            // Using the block name here speeds up performance in collection
            // forms, where each entry has the same full block name.
            // Including the type is important too, because if rows of a
            // collection form have different types (dynamically), they should
            // be rendered differently.
            // https://github.com/symfony/symfony/issues/5038
            Abstract_Renderer_Engine::CACHE_KEY_VAR => $unique_block_prefix . '_' . $form->get_config()->get_type()->get_block_prefix(),
        ]);
    }
    public function configure_options(Options_Resolver $resolver): void
    {
        $resolver->set_defaults(['block_name' => null, 'block_prefix' => null, 'disabled' => false, 'label' => null, 'label_format' => null, 'row_attr' => [], 'label_html' => false, 'label_translation_parameters' => [], 'attr_translation_parameters' => [], 'attr' => [], 'translation_domain' => null, 'auto_initialize' => true, 'priority' => 0, 'form_attr' => false]);
        $resolver->set_allowed_types('block_prefix', ['null', 'string']);
        $resolver->set_allowed_types('attr', 'array');
        $resolver->set_allowed_types('row_attr', 'array');
        $resolver->set_allowed_types('label_html', 'bool');
        $resolver->set_allowed_types('priority', 'int');
        $resolver->set_allowed_types('form_attr', ['bool', 'string']);
        $resolver->set_info('priority', 'The form rendering priority (higher priorities will be rendered first)');
    }
}