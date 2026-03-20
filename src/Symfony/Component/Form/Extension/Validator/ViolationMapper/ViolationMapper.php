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
namespace Symfony\Component\Form\Extension\Validator\Violation_Mapper;

use Symfony\Component\Form\File_Upload_Error;
use Symfony\Component\Form\Form_Error;
use Symfony\Component\Form\Form_Interface;
use Symfony\Component\Form\Form_Renderer_Interface;
use Symfony\Component\Form\Util\Inherit_Data_Aware_Iterator;
use Symfony\Component\Property_Access\Property_Path_Builder;
use Symfony\Component\Property_Access\Property_Path_Iterator;
use Symfony\Component\Property_Access\Property_Path_Iterator_Interface;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraint_Violation;
use Symfony\Contracts\Translation\Translator_Interface;
/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class Violation_Mapper implements Violation_Mapper_Interface
{
    private bool $allow_non_synchronized = false;
    public function __construct(private readonly ?Form_Renderer_Interface $form_renderer = null, private readonly ?Translator_Interface $translator = null)
    {
    }
    public function map_violation(Constraint_Violation $violation, Form_Interface $form, bool $allow_non_synchronized = false): void
    {
        $this->allow_non_synchronized = $allow_non_synchronized;
        // The scope is the currently found most specific form that
        // an error should be mapped to. After setting the scope, the
        // mapper will try to continue to find more specific matches in
        // the children of scope. If it cannot, the error will be
        // mapped to this scope.
        $scope = null;
        $violation_path = null;
        $relative_path = null;
        $match = false;
        // Don't create a ViolationPath instance for empty property paths
        if ('' !== $violation->get_property_path()) {
            $violation_path = new Violation_Path($violation->get_property_path());
            $relative_path = $this->reconstruct_path($violation_path, $form);
        }
        // This case happens if the violation path is empty and thus
        // the violation should be mapped to the root form
        if (null === $violation_path) {
            $scope = $form;
        }
        // In general, mapping happens from the root form to the leaf forms
        // First, the rules of the root form are applied to determine
        // the subsequent descendant. The rules of this descendant are then
        // applied to find the next and so on, until we have found the
        // most specific form that matches the violation.
        // If any of the forms found in this process is not synchronized,
        // mapping is aborted. Non-synchronized forms could not reverse
        // transform the value entered by the user, thus any further violations
        // caused by the (invalid) reverse transformed value should be
        // ignored.
        if (null !== $relative_path) {
            // Set the scope to the root of the relative path
            // This root will usually be $form. If the path contains
            // an unmapped form though, the last unmapped form found
            // will be the root of the path.
            $scope = $relative_path->get_root();
            $it = new Property_Path_Iterator($relative_path);
            while ($this->accepts_errors($scope) && null !== $child = $this->match_child($scope, $it)) {
                $scope = $child;
                $it->next();
                $match = true;
            }
        }
        // This case happens if an error happened in the data under a
        // form inheriting its parent data that does not match any of the
        // children of that form.
        if (null !== $violation_path && !$match) {
            // If we could not map the error to anything more specific
            // than the root element, map it to the innermost directly
            // mapped form of the violation path
            // e.g. "children[foo].children[bar].data.baz"
            // Here the innermost directly mapped child is "bar"
            $scope = $form;
            $it = new Violation_Path_Iterator($violation_path);
            // Note: acceptsErrors() will always return true for forms inheriting
            // their parent data, because these forms can never be non-synchronized
            // (they don't do any data transformation on their own)
            while ($this->accepts_errors($scope) && $it->valid() && $it->maps_form()) {
                if (!$scope->has($it->current())) {
                    // Break if we find a reference to a non-existing child
                    break;
                }
                $scope = $scope->get($it->current());
                $it->next();
            }
        }
        // Follow dot rules until we have the final target
        $mapping = $scope->get_config()->get_option('error_mapping');
        while ($this->accepts_errors($scope) && isset($mapping['.'])) {
            $dot_rule = new Mapping_Rule($scope, '.', $mapping['.']);
            $scope = $dot_rule->get_target();
            $mapping = $scope->get_config()->get_option('error_mapping');
        }
        // Only add the error if the form is synchronized
        if ($this->accepts_errors($scope)) {
            if ($violation->get_constraint() instanceof File && (string) \UPLOAD_ERR_INI_SIZE === $violation->get_code()) {
                $errors_target = $scope;
                while (null !== $errors_target->get_parent() && $errors_target->get_config()->get_error_bubbling()) {
                    $errors_target = $errors_target->get_parent();
                }
                $errors = $errors_target->get_errors();
                $errors_target->clear_errors();
                foreach ($errors as $error) {
                    if (!$error instanceof File_Upload_Error) {
                        $errors_target->add_error($error);
                    }
                }
            }
            $message = $violation->get_message();
            $message_template = $violation->get_message_template();
            if (str_contains($message, '{{ label }}') || str_contains($message_template, '{{ label }}')) {
                $form = $scope;
                do {
                    $label_format = $form->get_config()->get_option('label_format');
                } while (null === $label_format && null !== $form = $form->get_parent());
                if (null !== $label_format) {
                    $label = str_replace(['%name%', '%id%'], [$scope->get_name(), (string) $scope->get_property_path()], $label_format);
                } else {
                    $label = $scope->get_config()->get_option('label');
                }
                if (false !== $label) {
                    if (null === $label && null !== $this->form_renderer) {
                        $label = $this->form_renderer->humanize($scope->get_name());
                    } else {
                        $label ??= $scope->get_name();
                    }
                    if (null !== $this->translator) {
                        $form = $scope;
                        $translation_parameters[] = $form->get_config()->get_option('label_translation_parameters', []);
                        do {
                            $translation_domain = $form->get_config()->get_option('translation_domain');
                            array_unshift($translation_parameters, $form->get_config()->get_option('label_translation_parameters', []));
                        } while (null === $translation_domain && null !== $form = $form->get_parent());
                        $translation_parameters = array_merge([], ...$translation_parameters);
                        $label = $this->translator->trans($label, $translation_parameters, $translation_domain);
                    }
                    $message = str_replace('{{ label }}', $label, $message);
                    $message_template = str_replace('{{ label }}', $label, $message_template);
                }
            }
            $scope->add_error(new Form_Error($message, $message_template, $violation->get_parameters(), $violation->get_plural(), $violation));
        }
    }
    /**
     * Tries to match the beginning of the property path at the
     * current position against the children of the scope.
     *
     * If a matching child is found, it is returned. Otherwise
     * null is returned.
     */
    private function match_child(Form_Interface $form, Property_Path_Iterator_Interface $it): ?Form_Interface
    {
        $target = null;
        $chunk = '';
        $found_at_index = null;
        // Construct mapping rules for the given form
        /** @var MappingRule[] $rules */
        $rules = [];
        foreach ($form->get_config()->get_option('error_mapping') as $property_path => $target_path) {
            // Dot rules are considered at the very end
            if ('.' !== $property_path) {
                $rules[] = new Mapping_Rule($form, $property_path, $target_path);
            }
        }
        /** @var FormInterface[] $children */
        $children = iterator_to_array(new \Recursive_Iterator_Iterator(new Inherit_Data_Aware_Iterator($form)), false);
        while ($it->valid()) {
            if ($it->is_index()) {
                $chunk .= '[' . $it->current() . ']';
            } else {
                $chunk .= ('' === $chunk ? '' : '.') . $it->current();
            }
            // Test mapping rules as long as we have any
            foreach ($rules as $key => $rule) {
                // Mapping rule matches completely, terminate.
                if (null !== $form = $rule->match($chunk)) {
                    return $form;
                }
                // Keep only rules that have $chunk as prefix
                if (!$rule->is_prefix($chunk)) {
                    unset($rules[$key]);
                }
            }
            foreach ($children as $i => $child) {
                $child_path = (string) $child->get_property_path();
                if ($child_path === $chunk) {
                    $target = $child;
                    $found_at_index = $it->key();
                } elseif (str_starts_with($child_path, $chunk)) {
                    continue;
                }
                unset($children[$i]);
            }
            $it->next();
        }
        if (null !== $found_at_index) {
            $it->seek($found_at_index);
        }
        return $target;
    }
    /**
     * Reconstructs a property path from a violation path and a form tree.
     */
    private function reconstruct_path(Violation_Path $violation_path, Form_Interface $origin): ?Relative_Path
    {
        $property_path_builder = new Property_Path_Builder($violation_path);
        $it = $violation_path->getIterator();
        $scope = $origin;
        // Remember the current index in the builder
        $i = 0;
        // Expand elements that map to a form (like "children[address]")
        for ($it->rewind(); $it->valid() && $it->maps_form(); $it->next()) {
            if (!$scope->has($it->current())) {
                // Scope relates to a form that does not exist
                // Bail out
                break;
            }
            // Process child form
            $scope = $scope->get($it->current());
            if ($scope->get_config()->get_inherit_data()) {
                // Form inherits its parent data
                // Cut the piece out of the property path and proceed
                $property_path_builder->remove($i);
            } else {
                $property_path = $scope->get_property_path();
                if (null === $property_path) {
                    // Property path of a mapped form is null
                    // Should not happen, bail out
                    break;
                }
                $property_path_builder->replace($i, 1, $property_path);
                $i += $property_path->get_length();
            }
        }
        $final_path = $property_path_builder->get_property_path();
        return null !== $final_path ? new Relative_Path($origin, $final_path) : null;
    }
    private function accepts_errors(Form_Interface $form): bool
    {
        return $this->allow_non_synchronized || $form->is_synchronized();
    }
}