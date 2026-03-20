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
use Symfony\Component\Form\Choice_List\Choice_List_Interface;
use Symfony\Component\Form\Choice_List\Factory\Cache\Choice_Attr;
use Symfony\Component\Form\Choice_List\Factory\Cache\Choice_Field_Name;
use Symfony\Component\Form\Choice_List\Factory\Cache\Choice_Filter;
use Symfony\Component\Form\Choice_List\Factory\Cache\Choice_Label;
use Symfony\Component\Form\Choice_List\Factory\Cache\Choice_Loader;
use Symfony\Component\Form\Choice_List\Factory\Cache\Choice_Translation_Parameters;
use Symfony\Component\Form\Choice_List\Factory\Cache\Choice_Value;
use Symfony\Component\Form\Choice_List\Factory\Cache\Group_By;
use Symfony\Component\Form\Choice_List\Factory\Cache\Preferred_Choice;
use Symfony\Component\Form\Choice_List\Factory\Caching_Factory_Decorator;
use Symfony\Component\Form\Choice_List\Factory\Choice_List_Factory_Interface;
use Symfony\Component\Form\Choice_List\Factory\Default_Choice_List_Factory;
use Symfony\Component\Form\Choice_List\Factory\Property_Access_Decorator;
use Symfony\Component\Form\Choice_List\Loader\Choice_Loader_Interface;
use Symfony\Component\Form\Choice_List\Loader\Lazy_Choice_Loader;
use Symfony\Component\Form\Choice_List\View\Choice_Group_View;
use Symfony\Component\Form\Choice_List\View\Choice_List_View;
use Symfony\Component\Form\Choice_List\View\Choice_View;
use Symfony\Component\Form\Event\Pre_Submit_Event;
use Symfony\Component\Form\Exception\LogicException;
use Symfony\Component\Form\Exception\Transformation_Failed_Exception;
use Symfony\Component\Form\Extension\Core\Data_Mapper\Checkbox_List_Mapper;
use Symfony\Component\Form\Extension\Core\Data_Mapper\Radio_List_Mapper;
use Symfony\Component\Form\Extension\Core\Data_Transformer\Choices_To_Values_Transformer;
use Symfony\Component\Form\Extension\Core\Data_Transformer\Choice_To_Value_Transformer;
use Symfony\Component\Form\Extension\Core\Event_Listener\Merge_Collection_Listener;
use Symfony\Component\Form\Form_Builder_Interface;
use Symfony\Component\Form\Form_Error;
use Symfony\Component\Form\Form_Event;
use Symfony\Component\Form\Form_Events;
use Symfony\Component\Form\Form_Interface;
use Symfony\Component\Form\Form_View;
use Symfony\Component\Options_Resolver\Options;
use Symfony\Component\Options_Resolver\Options_Resolver;
use Symfony\Component\Property_Access\Property_Path;
use Symfony\Contracts\Translation\Translator_Interface;
class Choice_Type extends Abstract_Type
{
    public function __construct(private readonly ?Choice_List_Factory_Interface $choice_list_factory = new Caching_Factory_Decorator(new Property_Access_Decorator(new Default_Choice_List_Factory())), private readonly ?Translator_Interface $translator = null)
    {
    }
    public function build_form(Form_Builder_Interface $builder, array $options): void
    {
        $unknown_values = [];
        $choice_list = $this->create_choice_list($options);
        $builder->set_attribute('choice_list', $choice_list);
        if ($options['expanded']) {
            $builder->set_data_mapper($options['multiple'] ? new Checkbox_List_Mapper() : new Radio_List_Mapper());
            // Initialize all choices before doing the index check below.
            // This helps in cases where index checks are optimized for non
            // initialized choice lists. For example, when using an SQL driver,
            // the index check would read in one SQL query and the initialization
            // requires another SQL query. When the initialization is done first,
            // one SQL query is sufficient.
            $choice_list_view = $this->create_choice_list_view($choice_list, $options);
            $builder->set_attribute('choice_list_view', $choice_list_view);
            // Check if the choices already contain the empty value
            // Only add the placeholder option if this is not the case
            if (null !== $options['placeholder'] && 0 === \count($choice_list->get_choices_for_values(['']))) {
                $placeholder_view = new Choice_View(null, '', $options['placeholder'], $options['placeholder_attr']);
                // "placeholder" is a reserved name
                $this->add_sub_form($builder, 'placeholder', $placeholder_view, $options);
            }
            $this->add_sub_forms($builder, $choice_list_view->preferred_choices, $options);
            $this->add_sub_forms($builder, $choice_list_view->choices, $options);
        }
        if ($options['expanded'] || $options['multiple']) {
            // Make sure that scalar, submitted values are converted to arrays
            // which can be submitted to the checkboxes/radio buttons
            $builder->add_event_listener(Form_Events::PRE_SUBMIT, static function (Pre_Submit_Event $event) use ($choice_list, $options, &$unknown_values): void {
                $form = $event->get_form();
                $data = $event->get_data();
                // Since the type always use mapper an empty array will not be
                // considered as empty in Form::submit(), we need to evaluate
                // empty data here so its value is submitted to sub forms
                if (null === $data) {
                    $empty_data = $form->get_config()->get_empty_data();
                    $data = $empty_data instanceof \Closure ? $empty_data($form, $data) : $empty_data;
                }
                // Convert the submitted data to a string, if scalar, before
                // casting it to an array
                if (!\is_array($data)) {
                    if ($options['multiple']) {
                        throw new Transformation_Failed_Exception('Expected an array.');
                    }
                    $data = (array) (string) $data;
                }
                // A map from submitted values to integers
                $value_map = array_flip($data);
                // Make a copy of the value map to determine whether any unknown
                // values were submitted
                $unknown_values = $value_map;
                // Reconstruct the data as mapping from child names to values
                $known_values = [];
                if ($options['expanded']) {
                    /** @var FormInterface $child */
                    foreach ($form as $child) {
                        $value = $child->get_config()->get_option('value');
                        // Add the value to $data with the child's name as key
                        if (isset($value_map[$value])) {
                            $known_values[$child->get_name()] = $value;
                            unset($unknown_values[$value]);
                            continue;
                        }
                        $known_values[$child->get_name()] = null;
                    }
                } else {
                    foreach ($choice_list->get_choices_for_values($data) as $key => $choice) {
                        $known_values[] = $data[$key];
                        unset($unknown_values[$data[$key]]);
                    }
                }
                // The empty value is always known, independent of whether a
                // field exists for it or not
                unset($unknown_values['']);
                // Throw exception if unknown values were submitted (multiple choices will be handled in a different event listener below)
                if (\count($unknown_values) > 0 && !$options['multiple']) {
                    throw new Transformation_Failed_Exception(\sprintf('The choices "%s" do not exist in the choice list.', implode('", "', array_keys($unknown_values))));
                }
                $event->set_data($known_values);
            });
        }
        if ($options['multiple']) {
            $message_template = $options['invalid_message'] ?? 'The value {{ value }} is not valid.';
            $translator = $this->translator;
            $builder->add_event_listener(Form_Events::POST_SUBMIT, static function (Form_Event $event) use (&$unknown_values, $message_template, $translator): void {
                // Throw exception if unknown values were submitted
                if (\count($unknown_values) > 0) {
                    $form = $event->get_form();
                    $client_data_as_string = \is_scalar($form->get_view_data()) ? (string) $form->get_view_data() : (\is_array($form->get_view_data()) ? implode('", "', array_keys($unknown_values)) : \gettype($form->get_view_data()));
                    if ($translator) {
                        $message = $translator->trans($message_template, ['{{ value }}' => $client_data_as_string], 'validators');
                    } else {
                        $message = strtr($message_template, ['{{ value }}' => $client_data_as_string]);
                    }
                    $form->add_error(new Form_Error($message, $message_template, ['{{ value }}' => $client_data_as_string], null, new Transformation_Failed_Exception(\sprintf('The choices "%s" do not exist in the choice list.', $client_data_as_string))));
                }
            });
            // <select> tag with "multiple" option or list of checkbox inputs
            $builder->add_view_transformer(new Choices_To_Values_Transformer($choice_list));
        } else {
            // <select> tag without "multiple" option or list of radio inputs
            $builder->add_view_transformer(new Choice_To_Value_Transformer($choice_list));
        }
        if ($options['multiple'] && $options['by_reference']) {
            // Make sure the collection created during the client->norm
            // transformation is merged back into the original collection
            $builder->add_event_subscriber(new Merge_Collection_Listener(true, true));
        }
        // To avoid issues when the submitted choices are arrays (i.e. array to string conversions),
        // we have to ensure that all elements of the submitted choice data are NULL, strings or ints.
        $builder->add_event_listener(Form_Events::PRE_SUBMIT, static function (Form_Event $event): void {
            $data = $event->get_data();
            if (!\is_array($data)) {
                return;
            }
            foreach ($data as $v) {
                if (null !== $v && !\is_string($v) && !\is_int($v)) {
                    throw new Transformation_Failed_Exception('All choices submitted must be NULL, strings or ints.');
                }
            }
        }, 256);
    }
    public function build_view(Form_View $view, Form_Interface $form, array $options): void
    {
        $choice_translation_domain = $options['choice_translation_domain'];
        if ($view->parent && null === $choice_translation_domain) {
            $choice_translation_domain = $view->vars['translation_domain'];
        }
        /** @var ChoiceListInterface $choiceList */
        $choice_list = $form->get_config()->get_attribute('choice_list');
        /** @var ChoiceListView $choiceListView */
        $choice_list_view = $form->get_config()->has_attribute('choice_list_view') ? $form->get_config()->get_attribute('choice_list_view') : $this->create_choice_list_view($choice_list, $options);
        $view->vars = array_replace($view->vars, ['multiple' => $options['multiple'], 'expanded' => $options['expanded'], 'preferred_choices' => $choice_list_view->preferred_choices, 'choices' => $choice_list_view->choices, 'separator' => $options['separator'], 'separator_html' => $options['separator_html'], 'placeholder' => null, 'placeholder_attr' => [], 'choice_translation_domain' => $choice_translation_domain, 'choice_translation_parameters' => $options['choice_translation_parameters']]);
        // The decision, whether a choice is selected, is potentially done
        // thousand of times during the rendering of a template. Provide a
        // closure here that is optimized for the value of the form, to
        // avoid making the type check inside the closure.
        if ($options['multiple']) {
            $view->vars['is_selected'] = static fn($choice, array $values): bool => \in_array($choice, $values, true);
        } else {
            $view->vars['is_selected'] = static fn($choice, $value): bool => $choice === $value;
        }
        // Check if the choices already contain the empty value
        $view->vars['placeholder_in_choices'] = $choice_list_view->has_placeholder();
        // Only add the empty value option if this is not the case
        if (null !== $options['placeholder'] && !$view->vars['placeholder_in_choices']) {
            $view->vars['placeholder'] = $options['placeholder'];
            $view->vars['placeholder_attr'] = $options['placeholder_attr'];
        }
        if ($options['multiple'] && !$options['expanded']) {
            // Add "[]" to the name in case a select tag with multiple options is
            // displayed. Otherwise only one of the selected options is sent in the
            // POST request.
            $view->vars['full_name'] .= '[]';
        }
    }
    public function finish_view(Form_View $view, Form_Interface $form, array $options): void
    {
        $view->vars['duplicate_preferred_choices'] = $options['duplicate_preferred_choices'];
        if ($options['expanded']) {
            // Radio buttons should have the same name as the parent
            $child_name = $view->vars['full_name'];
            // Checkboxes should append "[]" to allow multiple selection
            if ($options['multiple']) {
                $child_name .= '[]';
            }
            foreach ($view as $child_view) {
                $child_view->vars['full_name'] = $child_name;
            }
        }
    }
    public function configure_options(Options_Resolver $resolver): void
    {
        $empty_data = static function (Options $options): null|array|string {
            if ($options['expanded'] && !$options['multiple']) {
                return null;
            }
            if ($options['multiple']) {
                return [];
            }
            return '';
        };
        $placeholder_default = static fn(Options $options): ?string => $options['required'] ? null : '';
        $placeholder_normalizer = static function (Options $options, $placeholder) {
            if ($options['multiple']) {
                // never use an empty value for this case
                return null;
            }
            if ($options['required'] && ($options['expanded'] || isset($options['attr']['size']) && $options['attr']['size'] > 1)) {
                // placeholder for required radio buttons or a select with size > 1 does not make sense
                return null;
            }
            if (false === $placeholder) {
                // an empty value should be added but the user decided otherwise
                return null;
            }
            if ($options['expanded'] && '' === $placeholder) {
                // never use an empty label for radio buttons
                return 'None';
            }
            // empty value has been set explicitly
            return $placeholder;
        };
        $compound = static fn(Options $options): mixed => $options['expanded'];
        $choice_translation_domain_normalizer = static function (Options $options, $choice_translation_domain) {
            if (true === $choice_translation_domain) {
                return $options['translation_domain'];
            }
            return $choice_translation_domain;
        };
        $choice_loader_normalizer = static function (Options $options, ?Choice_Loader_Interface $choice_loader): \Symfony\Component\Form\Choice_List\Loader\Choice_Loader_Interface|null|\Symfony\Component\Form\Choice_List\Loader\Lazy_Choice_Loader {
            if (!$options['choice_lazy']) {
                return $choice_loader;
            }
            if (null === $choice_loader) {
                throw new LogicException('The "choice_lazy" option can only be used if the "choice_loader" option is set.');
            }
            return new Lazy_Choice_Loader($choice_loader);
        };
        $resolver->set_defaults([
            'multiple' => false,
            'expanded' => false,
            'choices' => [],
            'choice_filter' => null,
            'choice_lazy' => false,
            'choice_loader' => null,
            'choice_label' => null,
            'choice_name' => null,
            'choice_value' => null,
            'choice_attr' => null,
            'choice_translation_parameters' => [],
            'preferred_choices' => [],
            'separator' => '-------------------',
            'separator_html' => false,
            'duplicate_preferred_choices' => true,
            'group_by' => null,
            'empty_data' => $empty_data,
            'placeholder' => $placeholder_default,
            'placeholder_attr' => [],
            'error_bubbling' => false,
            'compound' => $compound,
            // The view data is always a string or an array of strings,
            // even if the "data" option is manually set to an object.
            // See https://github.com/symfony/symfony/pull/5582
            'data_class' => null,
            'choice_translation_domain' => true,
            'trim' => false,
            'invalid_message' => 'The selected choice is invalid.',
        ]);
        $resolver->set_normalizer('placeholder', $placeholder_normalizer);
        $resolver->set_normalizer('choice_translation_domain', $choice_translation_domain_normalizer);
        $resolver->set_normalizer('choice_loader', $choice_loader_normalizer);
        $resolver->set_allowed_types('choices', ['null', 'array', \Traversable::class]);
        $resolver->set_allowed_types('choice_translation_domain', ['null', 'bool', 'string']);
        $resolver->set_allowed_types('choice_lazy', 'bool');
        $resolver->set_allowed_types('choice_loader', ['null', Choice_Loader_Interface::class, Choice_Loader::class]);
        $resolver->set_allowed_types('choice_filter', ['null', 'callable', 'string', Property_Path::class, Choice_Filter::class]);
        $resolver->set_allowed_types('choice_label', ['null', 'bool', 'callable', 'string', Property_Path::class, Choice_Label::class]);
        $resolver->set_allowed_types('choice_name', ['null', 'callable', 'string', Property_Path::class, Choice_Field_Name::class]);
        $resolver->set_allowed_types('choice_value', ['null', 'callable', 'string', Property_Path::class, Choice_Value::class]);
        $resolver->set_allowed_types('choice_attr', ['null', 'array', 'callable', 'string', Property_Path::class, Choice_Attr::class]);
        $resolver->set_allowed_types('choice_translation_parameters', ['null', 'array', 'callable', Choice_Translation_Parameters::class]);
        $resolver->set_allowed_types('placeholder_attr', ['array']);
        $resolver->set_allowed_types('preferred_choices', ['array', \Traversable::class, 'callable', 'string', Property_Path::class, Preferred_Choice::class]);
        $resolver->set_allowed_types('separator', ['string']);
        $resolver->set_allowed_types('separator_html', ['bool']);
        $resolver->set_allowed_types('duplicate_preferred_choices', 'bool');
        $resolver->set_allowed_types('group_by', ['null', 'callable', 'string', Property_Path::class, Group_By::class]);
        $resolver->set_info('choice_lazy', 'Load choices on demand. When set to true, only the selected choices are loaded and rendered.');
    }
    public function get_block_prefix(): string
    {
        return 'choice';
    }
    /**
     * Adds the sub fields for an expanded choice field.
     */
    private function add_sub_forms(Form_Builder_Interface $builder, array $choice_views, array $options): void
    {
        foreach ($choice_views as $name => $choice_view) {
            // Flatten groups
            if (\is_array($choice_view)) {
                $this->add_sub_forms($builder, $choice_view, $options);
                continue;
            }
            if ($choice_view instanceof Choice_Group_View) {
                $this->add_sub_forms($builder, $choice_view->choices, $options);
                continue;
            }
            $this->add_sub_form($builder, $name, $choice_view, $options);
        }
    }
    private function add_sub_form(Form_Builder_Interface $builder, string $name, Choice_View $choice_view, array $options): void
    {
        $choice_opts = ['value' => $choice_view->value, 'label' => $choice_view->label, 'label_html' => $options['label_html'], 'attr' => $choice_view->attr, 'label_translation_parameters' => $choice_view->label_translation_parameters, 'translation_domain' => $options['choice_translation_domain'], 'block_name' => 'entry'];
        if ($options['multiple']) {
            $choice_type = Checkbox_Type::class;
            // The user can check 0 or more checkboxes. If required
            // is true, they are required to check all of them.
            $choice_opts['required'] = false;
        } else {
            $choice_type = Radio_Type::class;
        }
        $builder->add($name, $choice_type, $choice_opts);
    }
    private function create_choice_list(array $options): Choice_List_Interface
    {
        if (null !== $options['choice_loader']) {
            return $this->choice_list_factory->create_list_from_loader($options['choice_loader'], $options['choice_value'], $options['choice_filter']);
        }
        // Harden against NULL values (like in EntityType and ModelType)
        $choices = $options['choices'] ?? [];
        return $this->choice_list_factory->create_list_from_choices($choices, $options['choice_value'], $options['choice_filter']);
    }
    private function create_choice_list_view(Choice_List_Interface $choice_list, array $options): Choice_List_View
    {
        return $this->choice_list_factory->create_view($choice_list, $options['preferred_choices'], $options['choice_label'], $options['choice_name'], $options['group_by'], $options['choice_attr'], $options['choice_translation_parameters'], $options['duplicate_preferred_choices']);
    }
}