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
namespace Symfony\Component\Form\Extension\Validator\Constraints;

use Symfony\Component\Form\Form_Interface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\Composite;
use Symfony\Component\Validator\Constraints\Group_Sequence;
use Symfony\Component\Validator\Constraints\Valid;
use Symfony\Component\Validator\Constraint_Validator;
use Symfony\Component\Validator\Exception\Unexpected_Type_Exception;
/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class Form_Validator extends Constraint_Validator
{
    /**
     * @var \SplObjectStorage<FormInterface, array<int, string|string[]|GroupSequence>>
     */
    private \Spl_Object_Storage $resolved_groups;
    public function validate(mixed $form, Constraint $form_constraint): void
    {
        if (!$form_constraint instanceof Form) {
            throw new Unexpected_Type_Exception($form_constraint, Form::class);
        }
        if (!$form instanceof Form_Interface) {
            return;
        }
        /** @var FormInterface $form */
        $config = $form->get_config();
        $validator = $this->context->get_validator()->in_context($this->context);
        if ($form->is_submitted() && $form->is_synchronized()) {
            // Validate the form data only if transformation succeeded
            $groups = $this->get_validation_groups($form);
            if (!$groups) {
                return;
            }
            $data = $form->get_data();
            // Validate the data against its own constraints
            $validate_data_graph = $form->is_root() && (\is_object($data) || \is_array($data)) && (\is_array($groups) || $groups instanceof Group_Sequence && $groups->groups);
            // Validate the data against the constraints defined in the form
            /** @var Constraint[] $constraints */
            $constraints = $config->get_option('constraints', []);
            $has_children = $form->count() > 0;
            if ($has_children && $form->is_root()) {
                $this->resolved_groups = new \Spl_Object_Storage();
            }
            if ($groups instanceof Group_Sequence) {
                // Validate the data, the form AND nested fields in sequence
                $violations_count = $this->context->get_violations()->count();
                foreach ($groups->groups as $group) {
                    if ($validate_data_graph) {
                        $validator->at_path('data')->validate($data, null, $group);
                    }
                    if ($grouped_constraints = self::get_constraints_in_groups($constraints, $group)) {
                        $validator->at_path('data')->validate($data, $grouped_constraints, $group);
                    }
                    foreach ($form->all() as $field) {
                        if ($field->is_submitted()) {
                            // remember to validate this field in one group only
                            // otherwise resolving the groups would reuse the same
                            // sequence recursively, thus some fields could fail
                            // in different steps without breaking early enough
                            $this->resolved_groups[$field] = (array) $group;
                            $field_form_constraint = new Form();
                            $field_form_constraint->groups = $group;
                            $this->context->set_node($this->context->get_value(), $field, $this->context->get_metadata(), $this->context->get_property_path());
                            $validator->at_path(\sprintf('children[%s]', $field->get_name()))->validate($field, $field_form_constraint, $group);
                        }
                    }
                    if ($violations_count < $this->context->get_violations()->count()) {
                        break;
                    }
                }
            } else {
                if ($validate_data_graph) {
                    $validator->at_path('data')->validate($data, null, $groups);
                }
                $grouped_constraints = [];
                foreach ($constraints as $constraint) {
                    // For the "Valid" constraint, validate the data in all groups
                    if ($constraint instanceof Valid) {
                        if (\is_object($data) || \is_array($data)) {
                            $validator->at_path('data')->validate($data, $constraint, $groups);
                        }
                        continue;
                    }
                    // Otherwise validate a constraint only once for the first
                    // matching group
                    foreach ($groups as $group) {
                        if (\in_array($group, $constraint->groups, true)) {
                            $grouped_constraints[$group][] = $constraint;
                            // Prevent duplicate validation
                            if (!$constraint instanceof Composite) {
                                continue 2;
                            }
                        }
                    }
                }
                foreach ($grouped_constraints as $group => $constraint) {
                    $validator->at_path('data')->validate($data, $constraint, $group);
                }
                foreach ($form->all() as $field) {
                    if ($field->is_submitted()) {
                        $this->resolved_groups[$field] = $groups;
                        $this->context->set_node($this->context->get_value(), $field, $this->context->get_metadata(), $this->context->get_property_path());
                        $validator->at_path(\sprintf('children[%s]', $field->get_name()))->validate($field, $form_constraint);
                    }
                }
            }
            if ($has_children && $form->is_root()) {
                // destroy storage to avoid memory leaks
                $this->resolved_groups = new \Spl_Object_Storage();
            }
        } elseif (!$form->is_synchronized()) {
            $children_synchronized = true;
            /** @var FormInterface $child */
            foreach ($form as $child) {
                if (!$child->is_synchronized()) {
                    $children_synchronized = false;
                    $this->context->set_node($this->context->get_value(), $child, $this->context->get_metadata(), $this->context->get_property_path());
                    $validator->at_path(\sprintf('children[%s]', $child->get_name()))->validate($child, $form_constraint);
                }
            }
            // Mark the form with an error if it is not synchronized BUT all
            // of its children are synchronized. If any child is not
            // synchronized, an error is displayed there already and showing
            // a second error in its parent form is pointless, or worse, may
            // lead to duplicate errors if error bubbling is enabled on the
            // child.
            // See also https://github.com/symfony/symfony/issues/4359
            if ($children_synchronized) {
                $client_data_as_string = \is_scalar($form->get_view_data()) ? (string) $form->get_view_data() : get_debug_type($form->get_view_data());
                $failure = $form->get_transformation_failure();
                $this->context->set_constraint($form_constraint);
                $this->context->build_violation($failure->get_invalid_message() ?? $config->get_option('invalid_message'))->set_parameters(array_replace(['{{ value }}' => $client_data_as_string], $config->get_option('invalid_message_parameters'), $failure->get_invalid_message_parameters()))->set_invalid_value($form->get_view_data())->set_code(Form::NOT_SYNCHRONIZED_ERROR)->set_cause($failure)->add_violation();
            }
        }
        // Mark the form with an error if it contains extra fields
        if (!$config->get_option('allow_extra_fields') && \count($form->get_extra_data()) > 0) {
            $this->context->set_constraint($form_constraint);
            $this->context->build_violation($config->get_option('extra_fields_message', ''))->set_parameter('{{ extra_fields }}', '"' . implode('", "', array_keys($form->get_extra_data())) . '"')->set_plural(\count($form->get_extra_data()))->set_invalid_value($form->get_extra_data())->set_code(Form::NO_SUCH_FIELD_ERROR)->add_violation();
        }
    }
    /**
     * Returns the validation groups of the given form.
     *
     * @return GroupSequence|string[]|\Symfony\Component\Validator\Constraints\GroupSequence[]
     */
    private function get_validation_groups(Form_Interface $form): \Symfony\Component\Validator\Constraints\Group_Sequence|array
    {
        // Determine the clicked button of the complete form tree
        $clicked_button = null;
        if (method_exists($form, 'getClickedButton')) {
            $clicked_button = $form->get_clicked_button();
        }
        if (null !== $clicked_button) {
            $groups = $clicked_button->get_config()->get_option('validation_groups');
            if (null !== $groups) {
                return self::resolve_validation_groups($groups, $form);
            }
        }
        do {
            $groups = $form->get_config()->get_option('validation_groups');
            if (null !== $groups) {
                return self::resolve_validation_groups($groups, $form);
            }
            if (isset($this->resolved_groups[$form])) {
                return $this->resolved_groups[$form];
            }
            $form = $form->get_parent();
        } while (null !== $form);
        return [Constraint::DEFAULT_GROUP];
    }
    /**
     * Post-processes the validation groups option for a given form.
     *
     * @param string|GroupSequence|array<string|GroupSequence>|callable $groups The validation groups
     *
     * @return GroupSequence|array<string|GroupSequence>
     */
    private static function resolve_validation_groups(string|Group_Sequence|array|callable $groups, Form_Interface $form): Group_Sequence|array
    {
        if (!\is_string($groups) && \is_callable($groups)) {
            $groups = $groups($form);
        }
        if ($groups instanceof Group_Sequence) {
            return $groups;
        }
        return (array) $groups;
    }
    private static function get_constraints_in_groups(array $constraints, string|array $group): array
    {
        $groups = (array) $group;
        return array_filter($constraints, static function (Constraint $constraint) use ($groups): bool {
            foreach ($groups as $group) {
                if (\in_array($group, $constraint->groups, true)) {
                    return true;
                }
            }
            return false;
        });
    }
}