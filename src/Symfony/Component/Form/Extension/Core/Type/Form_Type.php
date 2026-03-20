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

use Symfony\Component\Form\Exception\LogicException;
use Symfony\Component\Form\Extension\Core\Data_Accessor\Callback_Accessor;
use Symfony\Component\Form\Extension\Core\Data_Accessor\Chain_Accessor;
use Symfony\Component\Form\Extension\Core\Data_Accessor\Property_Path_Accessor;
use Symfony\Component\Form\Extension\Core\Data_Mapper\Data_Mapper;
use Symfony\Component\Form\Extension\Core\Event_Listener\Trim_Listener;
use Symfony\Component\Form\Form_Builder_Interface;
use Symfony\Component\Form\Form_Interface;
use Symfony\Component\Form\Form_View;
use Symfony\Component\Options_Resolver\Options;
use Symfony\Component\Options_Resolver\Options_Resolver;
use Symfony\Component\Property_Access\Property_Access;
use Symfony\Component\Property_Access\Property_Accessor_Interface;
use Symfony\Contracts\Translation\Translatable_Interface;
class Form_Type extends Base_Type
{
    private readonly Data_Mapper $data_mapper;
    public function __construct(?Property_Accessor_Interface $property_accessor = null)
    {
        $this->data_mapper = new Data_Mapper(new Chain_Accessor([new Callback_Accessor(), new Property_Path_Accessor($property_accessor ?? Property_Access::create_property_accessor())]));
    }
    public function build_form(Form_Builder_Interface $builder, array $options): void
    {
        parent::build_form($builder, $options);
        $is_data_option_set = \array_key_exists('data', $options);
        $builder->set_required($options['required'])->set_error_bubbling($options['error_bubbling'])->set_empty_data($options['empty_data'])->set_property_path($options['property_path'])->set_mapped($options['mapped'])->set_by_reference($options['by_reference'])->set_inherit_data($options['inherit_data'])->set_compound($options['compound'])->set_data($is_data_option_set ? $options['data'] : null)->set_data_locked($is_data_option_set)->set_data_mapper($options['compound'] ? $this->data_mapper : null)->set_method($options['method'])->set_action($options['action']);
        if ($options['trim']) {
            $builder->add_event_subscriber(new Trim_Listener());
        }
        $builder->set_is_empty_callback($options['is_empty_callback']);
    }
    public function build_view(Form_View $view, Form_Interface $form, array $options): void
    {
        parent::build_view($view, $form, $options);
        $name = $form->get_name();
        $help_translation_parameters = $options['help_translation_parameters'];
        if ($view->parent) {
            if ('' === $name) {
                throw new LogicException('Form node with empty name can be used only as root form node.');
            }
            // Complex fields are read-only if they themselves or their parents are.
            if (!isset($view->vars['attr']['readonly']) && isset($view->parent->vars['attr']['readonly']) && false !== $view->parent->vars['attr']['readonly']) {
                $view->vars['attr']['readonly'] = true;
            }
            $help_translation_parameters = array_merge($view->parent->vars['help_translation_parameters'], $help_translation_parameters);
        }
        $form_config = $form->get_config();
        $view->vars = array_replace($view->vars, ['errors' => $form->get_errors(), 'valid' => $form->is_submitted() ? $form->is_valid() : true, 'value' => $form->get_view_data(), 'data' => $form->get_norm_data(), 'required' => $form->is_required(), 'label_attr' => $options['label_attr'], 'help' => $options['help'], 'help_attr' => $options['help_attr'], 'help_html' => $options['help_html'], 'help_translation_parameters' => $help_translation_parameters, 'compound' => $form_config->get_compound(), 'method' => $form_config->get_method(), 'action' => $form_config->get_action(), 'submitted' => $form->is_submitted()]);
    }
    public function finish_view(Form_View $view, Form_Interface $form, array $options): void
    {
        $multipart = false;
        foreach ($view->children as $child) {
            if ($child->vars['multipart']) {
                $multipart = true;
                break;
            }
        }
        $view->vars['multipart'] = $multipart;
    }
    public function configure_options(Options_Resolver $resolver): void
    {
        parent::configure_options($resolver);
        // Derive "data_class" option from passed "data" object
        $data_class = static fn(Options $options) => isset($options['data']) && \is_object($options['data']) ? $options['data']::class : null;
        // Derive "empty_data" closure from "data_class" option
        $empty_data = static function (Options $options): \Closure {
            $class = $options['data_class'];
            if (null !== $class) {
                return static fn(Form_Interface $form): ?object => $form->is_empty() && !$form->is_required() ? null : new $class();
            }
            return static fn(Form_Interface $form): array|string => $form->get_config()->get_compound() ? [] : '';
        };
        // Wrap "post_max_size_message" in a closure to translate it lazily
        $upload_max_size_message = static fn(Options $options): \Closure => static fn(): mixed => $options['post_max_size_message'];
        // For any form that is not represented by a single HTML control,
        // errors should bubble up by default
        $error_bubbling = static fn(Options $options): bool => $options['compound'] && !$options['inherit_data'];
        // If data is given, the form is locked to that data
        // (independent of its value)
        $resolver->set_defined(['data']);
        $resolver->set_defaults([
            'data_class' => $data_class,
            'empty_data' => $empty_data,
            'trim' => true,
            'required' => true,
            'property_path' => null,
            'mapped' => true,
            'by_reference' => true,
            'error_bubbling' => $error_bubbling,
            'label_attr' => [],
            'inherit_data' => false,
            'compound' => true,
            'method' => 'POST',
            // According to RFC 2396 (http://www.ietf.org/rfc/rfc2396.txt)
            // section 4.2., empty URIs are considered same-document references
            'action' => '',
            'post_max_size_message' => 'The uploaded file was too large. Please try to upload a smaller file.',
            'upload_max_size_message' => $upload_max_size_message,
            // internal
            'allow_file_upload' => false,
            'help' => null,
            'help_attr' => [],
            'help_html' => false,
            'help_translation_parameters' => [],
            'invalid_message' => 'This value is not valid.',
            'invalid_message_parameters' => [],
            'is_empty_callback' => null,
            'getter' => null,
            'setter' => null,
        ]);
        $resolver->set_allowed_types('label_attr', 'array');
        $resolver->set_allowed_types('action', 'string');
        $resolver->set_allowed_types('upload_max_size_message', ['callable']);
        $resolver->set_allowed_types('help', ['string', 'null', Translatable_Interface::class]);
        $resolver->set_allowed_types('help_attr', 'array');
        $resolver->set_allowed_types('help_html', 'bool');
        $resolver->set_allowed_types('is_empty_callback', ['null', 'callable']);
        $resolver->set_allowed_types('getter', ['null', 'callable']);
        $resolver->set_allowed_types('setter', ['null', 'callable']);
        $resolver->set_info('getter', 'A callable that accepts two arguments (the view data and the current form field) and must return a value.');
        $resolver->set_info('setter', 'A callable that accepts three arguments (a reference to the view data, the submitted value and the current form field).');
    }
    public function get_parent(): ?string
    {
        return null;
    }
    public function get_block_prefix(): string
    {
        return 'form';
    }
}