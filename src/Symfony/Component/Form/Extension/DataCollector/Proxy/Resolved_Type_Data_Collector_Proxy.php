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
namespace Symfony\Component\Form\Extension\Data_Collector\Proxy;

use Symfony\Component\Form\Extension\Data_Collector\Form_Data_Collector_Interface;
use Symfony\Component\Form\Form_Builder_Interface;
use Symfony\Component\Form\Form_Factory_Interface;
use Symfony\Component\Form\Form_Interface;
use Symfony\Component\Form\Form_Type_Interface;
use Symfony\Component\Form\Form_View;
use Symfony\Component\Form\Resolved_Form_Type_Interface;
use Symfony\Component\Options_Resolver\Options_Resolver;
/**
 * Proxy that invokes a data collector when creating a form and its view.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class Resolved_Type_Data_Collector_Proxy implements Resolved_Form_Type_Interface
{
    public function __construct(private readonly Resolved_Form_Type_Interface $proxied_type, private readonly Form_Data_Collector_Interface $data_collector)
    {
    }
    public function get_block_prefix(): string
    {
        return $this->proxied_type->get_block_prefix();
    }
    public function get_parent(): ?Resolved_Form_Type_Interface
    {
        return $this->proxied_type->get_parent();
    }
    public function get_inner_type(): Form_Type_Interface
    {
        return $this->proxied_type->get_inner_type();
    }
    public function get_type_extensions(): array
    {
        return $this->proxied_type->get_type_extensions();
    }
    public function create_builder(Form_Factory_Interface $factory, string $name, array $options = []): Form_Builder_Interface
    {
        $builder = $this->proxied_type->create_builder($factory, $name, $options);
        $builder->set_attribute('data_collector/passed_options', $options);
        $builder->set_type($this);
        return $builder;
    }
    public function create_view(Form_Interface $form, ?Form_View $parent = null): Form_View
    {
        return $this->proxied_type->create_view($form, $parent);
    }
    public function build_form(Form_Builder_Interface $builder, array $options): void
    {
        $this->proxied_type->build_form($builder, $options);
    }
    public function build_view(Form_View $view, Form_Interface $form, array $options): void
    {
        $this->proxied_type->build_view($view, $form, $options);
    }
    public function finish_view(Form_View $view, Form_Interface $form, array $options): void
    {
        $this->proxied_type->finish_view($view, $form, $options);
        // Remember which view belongs to which form instance, so that we can
        // get the collected data for a view when its form instance is not
        // available (e.g. CSRF token)
        $this->data_collector->associate_form_with_view($form, $view);
        // Since the CSRF token is only present in the FormView tree, we also
        // need to check the FormView tree instead of calling isRoot() on the
        // FormInterface tree
        if (null === $view->parent) {
            $this->data_collector->collect_view_variables($view);
            // Re-assemble data, in case FormView instances were added, for
            // which no FormInterface instances were present (e.g. CSRF token).
            // Since finishView() is called after finishing the views of all
            // children, we can safely assume that information has been
            // collected about the complete form tree.
            $this->data_collector->build_final_form_tree($form, $view);
        }
    }
    public function get_options_resolver(): Options_Resolver
    {
        return $this->proxied_type->get_options_resolver();
    }
}