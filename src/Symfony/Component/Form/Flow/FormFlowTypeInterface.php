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
namespace Symfony\Component\Form\Flow;

use Symfony\Component\Form\Form_Type_Interface;
use Symfony\Component\Form\Form_View;
/**
 * A type that should be converted into a {@link FormFlow} instance.
 *
 * @author Yonel Ceruto <open@yceruto.dev>
 */
interface Form_Flow_Type_Interface extends Form_Type_Interface
{
    /**
     * Builds the multistep form.
     *
     * This method is called for each multistep type. Type extensions can further
     * modify the multistep form.
     *
     * @param array<string, mixed> $options
     */
    public function build_form_flow(Form_Flow_Builder_Interface $builder, array $options): void;
    /**
     * Builds the multistep form view.
     *
     * This method is called for each multistep type. Type extensions can further
     * modify the view.
     *
     * A view of a multistep form is built before the views of the child forms are built.
     * This means that you cannot access child views in this method. If you need
     * to do so, move your logic to {@link finishViewFlow()} instead.
     *
     * @param array<string, mixed> $options
     */
    public function build_view_flow(Form_View $view, Form_Flow_Interface $form, array $options): void;
    /**
     * Finishes the multistep form view.
     *
     * This method gets called for each multistep type. Type extensions can further
     * modify the view.
     *
     * When this method is called, views of the multistep form's children have already
     * been built and finished and can be accessed. You should only implement
     * such logic in this method that actually accesses child views. For everything
     * else you are recommended to implement {@link buildViewFlow()} instead.
     *
     * @param array<string, mixed> $options
     */
    public function finish_view_flow(Form_View $view, Form_Flow_Interface $form, array $options): void;
}