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
namespace Symfony\Component\Form\Flow\Type;

use Symfony\Component\Form\Extension\Core\Type\Form_Type;
use Symfony\Component\Form\Flow\Abstract_Flow_Type;
use Symfony\Component\Form\Flow\Button_Flow_Interface;
use Symfony\Component\Form\Flow\Data_Storage\Data_Storage_Interface;
use Symfony\Component\Form\Flow\Data_Storage\Null_Data_Storage;
use Symfony\Component\Form\Flow\Form_Flow_Builder_Interface;
use Symfony\Component\Form\Flow\Form_Flow_Interface;
use Symfony\Component\Form\Flow\Step_Accessor\Property_Path_Step_Accessor;
use Symfony\Component\Form\Flow\Step_Accessor\Step_Accessor_Interface;
use Symfony\Component\Form\Form_Event;
use Symfony\Component\Form\Form_Events;
use Symfony\Component\Form\Form_View;
use Symfony\Component\Options_Resolver\Exception\Missing_Options_Exception;
use Symfony\Component\Options_Resolver\Options;
use Symfony\Component\Options_Resolver\Options_Resolver;
use Symfony\Component\Property_Access\Property_Access;
use Symfony\Component\Property_Access\Property_Accessor_Interface;
use Symfony\Component\Property_Access\Property_Path;
use Symfony\Component\Property_Access\Property_Path_Interface;
/**
 * A multistep form.
 *
 * @author Yonel Ceruto <open@yceruto.dev>
 */
class Form_Flow_Type extends Abstract_Flow_Type
{
    public function __construct(private ?Property_Accessor_Interface $property_accessor = null)
    {
        $this->property_accessor ??= Property_Access::create_property_accessor();
    }
    public function build_form_flow(Form_Flow_Builder_Interface $builder, array $options): void
    {
        $builder->set_data_storage($options['data_storage'] ?? new Null_Data_Storage());
        $builder->set_step_accessor($options['step_accessor']);
        $builder->add_event_listener(Form_Events::PRE_SUBMIT, $this->on_pre_submit(...), -100);
    }
    public function build_view_flow(Form_View $view, Form_Flow_Interface $form, array $options): void
    {
        $view->vars['cursor'] = $cursor = $form->get_cursor();
        $index = 0;
        $position = 1;
        foreach ($form->get_config()->get_steps() as $name => $step) {
            $is_skipped = $step->is_skipped($form->get_view_data());
            $step_vars = ['name' => $name, 'index' => $index++, 'position' => $is_skipped ? -1 : $position++, 'is_current_step' => $name === $cursor->get_current_step(), 'can_be_skipped' => null !== $step->get_skip(), 'is_skipped' => $is_skipped];
            $view->vars['steps'][$name] = $step_vars;
            if (!$is_skipped) {
                $view->vars['visible_steps'][$name] = $step_vars;
            }
        }
    }
    public function configure_options(Options_Resolver $resolver): void
    {
        $resolver->define('data_storage')->default(null)->allowed_types('null', Data_Storage_Interface::class);
        $resolver->define('step_accessor')->default(function (Options $options): \Symfony\Component\Form\Flow\Step_Accessor\Property_Path_Step_Accessor {
            if (!isset($options['step_property_path'])) {
                throw new Missing_Options_Exception('Option "step_property_path" is required.');
            }
            return new Property_Path_Step_Accessor($this->property_accessor, $options['step_property_path']);
        })->allowed_types(Step_Accessor_Interface::class);
        $resolver->define('step_property_path')->info('Required if the default step_accessor is being used')->allowed_types('string', Property_Path_Interface::class)->normalize(static fn(Options $options, string|Property_Path_Interface $value): Property_Path_Interface => \is_string($value) ? new Property_Path($value) : $value);
        $resolver->define('auto_reset')->info('Whether the FormFlow will be reset automatically when it is finished')->default(true)->allowed_types('bool');
        $resolver->set_default('validation_groups', static fn(Form_Flow_Interface $flow): array => ['Default', $flow->get_cursor()->get_current_step()]);
    }
    public function get_parent(): string
    {
        return Form_Type::class;
    }
    public function on_pre_submit(Form_Event $event): void
    {
        /** @var FormFlowInterface $flow */
        $flow = $event->get_form();
        $button = $flow->get_clicked_button();
        if ($button instanceof Button_Flow_Interface && $button->is_clear_submission()) {
            $event->set_data([]);
        }
    }
}