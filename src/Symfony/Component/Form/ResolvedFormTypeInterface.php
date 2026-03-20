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
namespace Symfony\Component\Form;

use Symfony\Component\Options_Resolver\Options_Resolver;
/**
 * A wrapper for a form type and its extensions.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
interface Resolved_Form_Type_Interface
{
    /**
     * Returns the prefix of the template block name for this type.
     */
    public function get_block_prefix(): string;
    /**
     * Returns the parent type.
     */
    public function get_parent(): ?self;
    /**
     * Returns the wrapped form type.
     */
    public function get_inner_type(): Form_Type_Interface;
    /**
     * Returns the extensions of the wrapped form type.
     *
     * @return FormTypeExtensionInterface[]
     */
    public function get_type_extensions(): array;
    /**
     * Creates a new form builder for this type.
     *
     * @param string $name The name for the builder
     */
    public function create_builder(Form_Factory_Interface $factory, string $name, array $options = []): Form_Builder_Interface;
    /**
     * Creates a new form view for a form of this type.
     */
    public function create_view(Form_Interface $form, ?Form_View $parent = null): Form_View;
    /**
     * Configures a form builder for the type hierarchy.
     */
    public function build_form(Form_Builder_Interface $builder, array $options): void;
    /**
     * Configures a form view for the type hierarchy.
     *
     * It is called before the children of the view are built.
     */
    public function build_view(Form_View $view, Form_Interface $form, array $options): void;
    /**
     * Finishes a form view for the type hierarchy.
     *
     * It is called after the children of the view have been built.
     */
    public function finish_view(Form_View $view, Form_Interface $form, array $options): void;
    /**
     * Returns the configured options resolver used for this type.
     */
    public function get_options_resolver(): Options_Resolver;
}