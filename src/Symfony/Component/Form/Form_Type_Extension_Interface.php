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
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
interface Form_Type_Extension_Interface
{
    /**
     * Gets the extended types.
     *
     * @return string[]
     */
    public static function get_extended_types(): iterable;
    public function configure_options(Options_Resolver $resolver): void;
    /**
     * Builds the form.
     *
     * This method is called after the extended type has built the form to
     * further modify it.
     *
     * @param array<string, mixed> $options
     *
     * @see FormTypeInterface::buildForm()
     */
    public function build_form(Form_Builder_Interface $builder, array $options): void;
    /**
     * Builds the view.
     *
     * This method is called after the extended type has built the view to
     * further modify it.
     *
     * @param array<string, mixed> $options
     *
     * @see FormTypeInterface::buildView()
     */
    public function build_view(Form_View $view, Form_Interface $form, array $options): void;
    /**
     * Finishes the view.
     *
     * This method is called after the extended type has finished the view to
     * further modify it.
     *
     * @param array<string, mixed> $options
     *
     * @see FormTypeInterface::finishView()
     */
    public function finish_view(Form_View $view, Form_Interface $form, array $options): void;
}