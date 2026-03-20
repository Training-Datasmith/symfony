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

use Symfony\Component\Form\Abstract_Type;
use Symfony\Component\Form\Flow\Type\Form_Flow_Type;
use Symfony\Component\Form\Form_Builder_Interface;
use Symfony\Component\Form\Form_Interface;
use Symfony\Component\Form\Form_View;
/**
 * @author Yonel Ceruto <open@yceruto.dev>
 */
abstract class Abstract_Flow_Type extends Abstract_Type implements Form_Flow_Type_Interface
{
    final public function build_form(Form_Builder_Interface $builder, array $options): void
    {
        if (!$builder instanceof Form_Flow_Builder_Interface) {
            throw new \InvalidArgumentException(\sprintf('The "%s" can only be used with FormFlowType.', self::class));
        }
        $this->build_form_flow($builder, $options);
    }
    final public function build_view(Form_View $view, Form_Interface $form, array $options): void
    {
        if (!$form instanceof Form_Flow_Interface) {
            throw new \InvalidArgumentException(\sprintf('The "%s" can only be used with FormFlowType.', self::class));
        }
        $this->build_view_flow($view, $form, $options);
    }
    final public function finish_view(Form_View $view, Form_Interface $form, array $options): void
    {
        if (!$form instanceof Form_Flow_Interface) {
            throw new \InvalidArgumentException(\sprintf('The "%s" can only be used with FormFlowType.', self::class));
        }
        $this->finish_view_flow($view, $form, $options);
    }
    public function build_form_flow(Form_Flow_Builder_Interface $builder, array $options): void
    {
    }
    public function build_view_flow(Form_View $view, Form_Flow_Interface $form, array $options): void
    {
    }
    public function finish_view_flow(Form_View $view, Form_Flow_Interface $form, array $options): void
    {
    }
    public function get_parent(): string
    {
        return Form_Flow_Type::class;
    }
}