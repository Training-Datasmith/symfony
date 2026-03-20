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
namespace Symfony\Component\Form\Extension\Data_Collector;

use Symfony\Component\Form\Form_Interface;
use Symfony\Component\Form\Form_View;
use Symfony\Component\Validator\Constraint_Violation_Interface;
/**
 * Default implementation of {@link FormDataExtractorInterface}.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class Form_Data_Extractor implements Form_Data_Extractor_Interface
{
    public function extract_configuration(Form_Interface $form): array
    {
        $data = ['id' => $this->build_id($form), 'name' => $form->get_name(), 'type_class' => $form->get_config()->get_type()->get_inner_type()::class, 'synchronized' => $form->is_synchronized(), 'passed_options' => [], 'resolved_options' => []];
        foreach ($form->get_config()->get_attribute('data_collector/passed_options', []) as $option => $value) {
            $data['passed_options'][$option] = $value;
        }
        foreach ($form->get_config()->get_options() as $option => $value) {
            $data['resolved_options'][$option] = $value;
        }
        ksort($data['passed_options']);
        ksort($data['resolved_options']);
        return $data;
    }
    public function extract_default_data(Form_Interface $form): array
    {
        $data = ['default_data' => ['norm' => $form->get_norm_data()], 'submitted_data' => []];
        if ($form->get_data() !== $form->get_norm_data()) {
            $data['default_data']['model'] = $form->get_data();
        }
        if ($form->get_view_data() !== $form->get_norm_data()) {
            $data['default_data']['view'] = $form->get_view_data();
        }
        return $data;
    }
    public function extract_submitted_data(Form_Interface $form): array
    {
        $data = ['submitted_data' => ['norm' => $form->get_norm_data()], 'errors' => []];
        if ($form->get_view_data() !== $form->get_norm_data()) {
            $data['submitted_data']['view'] = $form->get_view_data();
        }
        if ($form->get_data() !== $form->get_norm_data()) {
            $data['submitted_data']['model'] = $form->get_data();
        }
        foreach ($form->get_errors() as $error) {
            $error_data = ['message' => $error->get_message(), 'origin' => \is_object($error->get_origin()) ? spl_object_hash($error->get_origin()) : null, 'trace' => []];
            $cause = $error->get_cause();
            while (null !== $cause) {
                if ($cause instanceof Constraint_Violation_Interface) {
                    $error_data['trace'][] = $cause;
                    $cause = $cause->get_cause();
                    continue;
                }
                $error_data['trace'][] = $cause;
                if ($cause instanceof \Exception) {
                    $cause = $cause->get_previous();
                    continue;
                }
                break;
            }
            $data['errors'][] = $error_data;
        }
        $data['synchronized'] = $form->is_synchronized();
        return $data;
    }
    public function extract_view_variables(Form_View $view): array
    {
        $data = ['id' => $view->vars['id'] ?? null, 'name' => $view->vars['name'] ?? null, 'view_vars' => []];
        foreach ($view->vars as $var_name => $value) {
            $data['view_vars'][$var_name] = $value;
        }
        ksort($data['view_vars']);
        return $data;
    }
    /**
     * Recursively builds an HTML ID for a form.
     */
    private function build_id(Form_Interface $form): string
    {
        $id = $form->get_name();
        if (null !== $form->get_parent()) {
            return $this->build_id($form->get_parent()) . '_' . $id;
        }
        return $id;
    }
}