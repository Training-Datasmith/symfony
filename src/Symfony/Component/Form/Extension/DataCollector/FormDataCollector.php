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
use Symfony\Component\Http_Foundation\Request;
use Symfony\Component\Http_Foundation\Response;
use Symfony\Component\Http_Kernel\Data_Collector\Data_Collector;
use Symfony\Component\Validator\Constraint_Violation_Interface;
use Symfony\Component\Var_Dumper\Caster\Caster;
use Symfony\Component\Var_Dumper\Caster\Class_Stub;
use Symfony\Component\Var_Dumper\Caster\Stub_Caster;
use Symfony\Component\Var_Dumper\Cloner\Data;
use Symfony\Component\Var_Dumper\Cloner\Stub;
/**
 * Data collector for {@link FormInterface} instances.
 *
 * @author Robert Schönthal <robert.schoenthal@gmail.com>
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @final
 */
class Form_Data_Collector extends Data_Collector implements Form_Data_Collector_Interface
{
    /**
     * Stores the collected data per {@link FormInterface} instance.
     *
     * Uses the hashes of the forms as keys. This is preferable over using
     * {@link \SplObjectStorage}, because in this way no references are kept
     * to the {@link FormInterface} instances.
     */
    private array $data_by_form;
    /**
     * Stores the collected data per {@link FormView} instance.
     *
     * Uses the hashes of the views as keys. This is preferable over using
     * {@link \SplObjectStorage}, because in this way no references are kept
     * to the {@link FormView} instances.
     */
    private array $data_by_view;
    /**
     * Connects {@link FormView} with {@link FormInterface} instances.
     *
     * Uses the hashes of the views as keys and the hashes of the forms as
     * values. This is preferable over storing the objects directly, because
     * this way they can safely be discarded by the GC.
     */
    private array $forms_by_view;
    public function __construct(private readonly Form_Data_Extractor_Interface $data_extractor)
    {
        if (!class_exists(Class_Stub::class)) {
            throw new \LogicException(\sprintf('The VarDumper component is needed for using the "%s" class. Install symfony/var-dumper version 3.4 or above.', self::class));
        }
        $this->reset();
    }
    /**
     * Does nothing. The data is collected during the form event listeners.
     */
    public function collect(Request $request, Response $response, ?\Throwable $exception = null): void
    {
    }
    public function reset(): void
    {
        $this->data = ['forms' => [], 'forms_by_hash' => [], 'nb_errors' => 0];
    }
    public function associate_form_with_view(Form_Interface $form, Form_View $view): void
    {
        $this->forms_by_view[spl_object_hash($view)] = spl_object_hash($form);
    }
    public function collect_configuration(Form_Interface $form): void
    {
        $hash = spl_object_hash($form);
        if (!isset($this->data_by_form[$hash])) {
            $this->data_by_form[$hash] = [];
        }
        $this->data_by_form[$hash] = array_replace($this->data_by_form[$hash], $this->data_extractor->extract_configuration($form));
        foreach ($form as $child) {
            $this->collect_configuration($child);
        }
    }
    public function collect_default_data(Form_Interface $form): void
    {
        $hash = spl_object_hash($form);
        if (!isset($this->data_by_form[$hash])) {
            // field was created by form event
            $this->collect_configuration($form);
        }
        $this->data_by_form[$hash] = array_replace($this->data_by_form[$hash], $this->data_extractor->extract_default_data($form));
        foreach ($form as $child) {
            $this->collect_default_data($child);
        }
    }
    public function collect_submitted_data(Form_Interface $form): void
    {
        $hash = spl_object_hash($form);
        if (!isset($this->data_by_form[$hash])) {
            // field was created by form event
            $this->collect_configuration($form);
            $this->collect_default_data($form);
        }
        $this->data_by_form[$hash] = array_replace($this->data_by_form[$hash], $this->data_extractor->extract_submitted_data($form));
        // Count errors
        if (isset($this->data_by_form[$hash]['errors'])) {
            $this->data['nb_errors'] += \count($this->data_by_form[$hash]['errors']);
        }
        foreach ($form as $child) {
            $this->collect_submitted_data($child);
            // Expand current form if there are children with errors
            if (empty($this->data_by_form[$hash]['has_children_error'])) {
                $child_data = $this->data_by_form[spl_object_hash($child)];
                $this->data_by_form[$hash]['has_children_error'] = !empty($child_data['has_children_error']) || !empty($child_data['errors']);
            }
        }
    }
    public function collect_view_variables(Form_View $view): void
    {
        $hash = spl_object_hash($view);
        if (!isset($this->data_by_view[$hash])) {
            $this->data_by_view[$hash] = [];
        }
        $this->data_by_view[$hash] = array_replace($this->data_by_view[$hash], $this->data_extractor->extract_view_variables($view));
        foreach ($view->children as $child) {
            $this->collect_view_variables($child);
        }
    }
    public function build_preliminary_form_tree(Form_Interface $form): void
    {
        $this->data['forms'][$form->get_name()] =& $this->recursive_build_preliminary_form_tree($form, $this->data['forms_by_hash']);
    }
    public function build_final_form_tree(Form_Interface $form, Form_View $view): void
    {
        $this->data['forms'][$form->get_name()] =& $this->recursive_build_final_form_tree($form, $view, $this->data['forms_by_hash']);
    }
    public function get_name(): string
    {
        return 'form';
    }
    public function get_data(): array|Data
    {
        return $this->data;
    }
    public function __serialize(): array
    {
        foreach ($this->data['forms_by_hash'] as &$form) {
            if (isset($form['type_class']) && !$form['type_class'] instanceof Class_Stub) {
                $form['type_class'] = new Class_Stub($form['type_class']);
            }
        }
        return ['data' => $this->data = $this->clone_var($this->data)];
    }
    protected function get_casters(): array
    {
        return parent::get_casters() + [\Exception::class => static function (\Exception $e, array $a, Stub $s): array {
            foreach (["\x00Exception\x00previous", "\x00Exception\x00trace"] as $k) {
                if (isset($a[$k])) {
                    unset($a[$k]);
                    ++$s->cut;
                }
            }
            return $a;
        }, Form_Interface::class => static fn(Form_Interface $f, array $a): array => [Caster::PREFIX_VIRTUAL . 'name' => $f->get_name(), Caster::PREFIX_VIRTUAL . 'type_class' => new Class_Stub($f->get_config()->get_type()->get_inner_type()::class)], Form_View::class => Stub_Caster::cut_internals(...), Constraint_Violation_Interface::class => static fn(Constraint_Violation_Interface $v, array $a): array => [Caster::PREFIX_VIRTUAL . 'root' => $v->get_root(), Caster::PREFIX_VIRTUAL . 'path' => $v->get_property_path(), Caster::PREFIX_VIRTUAL . 'value' => $v->get_invalid_value()]];
    }
    private function &recursive_build_preliminary_form_tree(Form_Interface $form, array &$output_by_hash): array
    {
        $hash = spl_object_hash($form);
        $output =& $output_by_hash[$hash];
        $output = $this->data_by_form[$hash] ?? [];
        $output['children'] = [];
        foreach ($form as $name => $child) {
            $output['children'][$name] =& $this->recursive_build_preliminary_form_tree($child, $output_by_hash);
        }
        return $output;
    }
    private function &recursive_build_final_form_tree(?Form_Interface $form, Form_View $view, array &$output_by_hash): array
    {
        $view_hash = spl_object_hash($view);
        $form_hash = null;
        if (null !== $form) {
            $form_hash = spl_object_hash($form);
        } elseif (isset($this->forms_by_view[$view_hash])) {
            // The FormInterface instance of the CSRF token is never contained in
            // the FormInterface tree of the form, so we need to get the
            // corresponding FormInterface instance for its view in a different way
            $form_hash = $this->forms_by_view[$view_hash];
        }
        if (null !== $form_hash) {
            $output =& $output_by_hash[$form_hash];
        }
        $output = $this->data_by_view[$view_hash] ?? [];
        if (null !== $form_hash) {
            $output = array_replace($output, $this->data_by_form[$form_hash] ?? []);
        }
        $output['children'] = [];
        foreach ($view->children as $name => $child_view) {
            // The CSRF token, for example, is never added to the form tree.
            // It is only present in the view.
            $child_form = $form?->has($name) ? $form->get($name) : null;
            $output['children'][$name] =& $this->recursive_build_final_form_tree($child_form, $child_view, $output_by_hash);
        }
        return $output;
    }
}