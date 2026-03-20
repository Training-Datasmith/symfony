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

use Symfony\Component\Event_Dispatcher\Event_Dispatcher;
use Symfony\Component\Form\Exception\Unexpected_Type_Exception;
use Symfony\Component\Form\Flow\Button_Flow_Builder;
use Symfony\Component\Form\Flow\Button_Flow_Type_Interface;
use Symfony\Component\Form\Flow\Form_Flow_Builder;
use Symfony\Component\Form\Flow\Form_Flow_Type_Interface;
use Symfony\Component\Options_Resolver\Exception\Exception_Interface;
use Symfony\Component\Options_Resolver\Options_Resolver;
/**
 * A wrapper for a form type and its extensions.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class Resolved_Form_Type implements Resolved_Form_Type_Interface
{
    /**
     * @var FormTypeExtensionInterface[]
     */
    private readonly array $type_extensions;
    private Options_Resolver $options_resolver;
    /**
     * @param FormTypeExtensionInterface[] $typeExtensions
     */
    public function __construct(private readonly Form_Type_Interface $inner_type, array $type_extensions = [], private readonly ?Resolved_Form_Type_Interface $parent = null)
    {
        foreach ($type_extensions as $extension) {
            if (!$extension instanceof Form_Type_Extension_Interface) {
                throw new Unexpected_Type_Exception($extension, Form_Type_Extension_Interface::class);
            }
        }
        $this->type_extensions = $type_extensions;
    }
    public function get_block_prefix(): string
    {
        return $this->inner_type->get_block_prefix();
    }
    public function get_parent(): ?Resolved_Form_Type_Interface
    {
        return $this->parent;
    }
    public function get_inner_type(): Form_Type_Interface
    {
        return $this->inner_type;
    }
    public function get_type_extensions(): array
    {
        return $this->type_extensions;
    }
    public function create_builder(Form_Factory_Interface $factory, string $name, array $options = []): Form_Builder_Interface
    {
        try {
            $options = $this->get_options_resolver()->resolve($options);
        } catch (Exception_Interface $e) {
            throw new $e(\sprintf('An error has occurred resolving the options of the form "%s": ', get_debug_type($this->get_inner_type())) . $e->get_message(), $e->get_code(), $e);
        }
        // Should be decoupled from the specific option at some point
        $data_class = $options['data_class'] ?? null;
        $builder = $this->new_builder($name, $data_class, $factory, $options);
        $builder->set_type($this);
        return $builder;
    }
    public function create_view(Form_Interface $form, ?Form_View $parent = null): Form_View
    {
        return $this->new_view($parent);
    }
    public function build_form(Form_Builder_Interface $builder, array $options): void
    {
        $this->parent?->build_form($builder, $options);
        $this->inner_type->build_form($builder, $options);
        foreach ($this->type_extensions as $extension) {
            $extension->build_form($builder, $options);
        }
    }
    public function build_view(Form_View $view, Form_Interface $form, array $options): void
    {
        $this->parent?->build_view($view, $form, $options);
        $this->inner_type->build_view($view, $form, $options);
        foreach ($this->type_extensions as $extension) {
            $extension->build_view($view, $form, $options);
        }
    }
    public function finish_view(Form_View $view, Form_Interface $form, array $options): void
    {
        $this->parent?->finish_view($view, $form, $options);
        $this->inner_type->finish_view($view, $form, $options);
        foreach ($this->type_extensions as $extension) {
            $extension->finish_view($view, $form, $options);
        }
    }
    public function get_options_resolver(): Options_Resolver
    {
        if (!isset($this->options_resolver)) {
            if (null !== $this->parent) {
                $this->options_resolver = clone $this->parent->get_options_resolver();
            } else {
                $this->options_resolver = new Options_Resolver();
            }
            $this->inner_type->configure_options($this->options_resolver);
            foreach ($this->type_extensions as $extension) {
                $extension->configure_options($this->options_resolver);
            }
        }
        return $this->options_resolver;
    }
    /**
     * Creates a new builder instance.
     *
     * Override this method if you want to customize the builder class.
     */
    protected function new_builder(string $name, ?string $data_class, Form_Factory_Interface $factory, array $options): Form_Builder_Interface
    {
        if ($this->inner_type instanceof Button_Type_Interface) {
            return new Button_Builder($name, $options);
        }
        if ($this->inner_type instanceof Submit_Button_Type_Interface) {
            return new Submit_Button_Builder($name, $options);
        }
        if ($this->inner_type instanceof Button_Flow_Type_Interface) {
            return new Button_Flow_Builder($name, $options);
        }
        if ($this->inner_type instanceof Form_Flow_Type_Interface) {
            return new Form_Flow_Builder($name, $data_class, new Event_Dispatcher(), $factory, $options);
        }
        return new Form_Builder($name, $data_class, new Event_Dispatcher(), $factory, $options);
    }
    /**
     * Creates a new view instance.
     *
     * Override this method if you want to customize the view class.
     */
    protected function new_view(?Form_View $parent = null): Form_View
    {
        return new Form_View($parent);
    }
}