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
use Symfony\Component\Http_Kernel\Data_Collector\Data_Collector_Interface;
use Symfony\Component\Var_Dumper\Cloner\Data;
/**
 * Collects and structures information about forms.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
interface Form_Data_Collector_Interface extends Data_Collector_Interface
{
    /**
     * Stores configuration data of the given form and its children.
     */
    public function collect_configuration(Form_Interface $form): void;
    /**
     * Stores the default data of the given form and its children.
     */
    public function collect_default_data(Form_Interface $form): void;
    /**
     * Stores the submitted data of the given form and its children.
     */
    public function collect_submitted_data(Form_Interface $form): void;
    /**
     * Stores the view variables of the given form view and its children.
     */
    public function collect_view_variables(Form_View $view): void;
    /**
     * Specifies that the given objects represent the same conceptual form.
     */
    public function associate_form_with_view(Form_Interface $form, Form_View $view): void;
    /**
     * Assembles the data collected about the given form and its children as
     * a tree-like data structure.
     *
     * The result can be queried using {@link getData()}.
     */
    public function build_preliminary_form_tree(Form_Interface $form): void;
    /**
     * Assembles the data collected about the given form and its children as
     * a tree-like data structure.
     *
     * The result can be queried using {@link getData()}.
     *
     * Contrary to {@link buildPreliminaryFormTree()}, a {@link FormView}
     * object has to be passed. The tree structure of this view object will be
     * used for structuring the resulting data. That means, if a child is
     * present in the view, but not in the form, it will be present in the final
     * data array anyway.
     *
     * When {@link FormView} instances are present in the view tree, for which
     * no corresponding {@link FormInterface} objects can be found in the form
     * tree, only the view data will be included in the result. If a
     * corresponding {@link FormInterface} exists otherwise, call
     * {@link associateFormWithView()} before calling this method.
     */
    public function build_final_form_tree(Form_Interface $form, Form_View $view): void;
    /**
     * Returns all collected data.
     */
    public function get_data(): array|Data;
}