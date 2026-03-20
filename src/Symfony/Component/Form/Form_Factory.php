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

use Symfony\Component\Form\Extension\Core\Type\Form_Type;
use Symfony\Component\Form\Extension\Core\Type\Text_Type;
use Symfony\Component\Form\Flow\Form_Flow_Builder_Interface;
use Symfony\Component\Form\Flow\Form_Flow_Interface;
use Symfony\Component\Form\Flow\Form_Flow_Type_Interface;
class Form_Factory implements Form_Factory_Interface
{
    public function __construct(private readonly Form_Registry_Interface $registry)
    {
    }
    /**
     * @return ($type is class-string<FormFlowTypeInterface> ? FormFlowInterface : FormInterface)
     */
    public function create(string $type = Form_Type::class, mixed $data = null, array $options = []): Form_Interface
    {
        return $this->create_builder($type, $data, $options)->get_form();
    }
    /**
     * @return ($type is class-string<FormFlowTypeInterface> ? FormFlowInterface : FormInterface)
     */
    public function create_named(string $name, string $type = Form_Type::class, mixed $data = null, array $options = []): Form_Interface
    {
        return $this->create_named_builder($name, $type, $data, $options)->get_form();
    }
    public function create_for_property(string $class, string $property, mixed $data = null, array $options = []): Form_Interface
    {
        return $this->create_builder_for_property($class, $property, $data, $options)->get_form();
    }
    /**
     * @return ($type is class-string<FormFlowTypeInterface> ? FormFlowBuilderInterface : FormBuilderInterface)
     */
    public function create_builder(string $type = Form_Type::class, mixed $data = null, array $options = []): Form_Builder_Interface
    {
        return $this->create_named_builder($this->registry->get_type($type)->get_block_prefix(), $type, $data, $options);
    }
    /**
     * @return ($type is class-string<FormFlowTypeInterface> ? FormFlowBuilderInterface : FormBuilderInterface)
     */
    public function create_named_builder(string $name, string $type = Form_Type::class, mixed $data = null, array $options = []): Form_Builder_Interface
    {
        if (null !== $data && !\array_key_exists('data', $options)) {
            $options['data'] = $data;
        }
        $type = $this->registry->get_type($type);
        $builder = $type->create_builder($this, $name, $options);
        if ($builder instanceof Form_Flow_Builder_Interface) {
            $builder->set_initial_options($options);
        }
        // Explicitly call buildForm() in order to be able to override either
        // createBuilder() or buildForm() in the resolved form type
        $type->build_form($builder, $builder->get_options());
        return $builder;
    }
    public function create_builder_for_property(string $class, string $property, mixed $data = null, array $options = []): Form_Builder_Interface
    {
        if (null === $guesser = $this->registry->get_type_guesser()) {
            return $this->create_named_builder($property, Text_Type::class, $data, $options);
        }
        $type_guess = $guesser->guess_type($class, $property);
        $max_length_guess = $guesser->guess_max_length($class, $property);
        $required_guess = $guesser->guess_required($class, $property);
        $pattern_guess = $guesser->guess_pattern($class, $property);
        $type = $type_guess ? $type_guess->get_type() : Text_Type::class;
        $max_length = $max_length_guess?->get_value();
        $pattern = $pattern_guess?->get_value();
        if (null !== $pattern) {
            $options = array_replace_recursive(['attr' => ['pattern' => $pattern]], $options);
        }
        if (null !== $max_length) {
            $options = array_replace_recursive(['attr' => ['maxlength' => $max_length]], $options);
        }
        if ($required_guess) {
            $options = array_merge(['required' => $required_guess->get_value()], $options);
        }
        // user options may override guessed options
        if ($type_guess) {
            $attrs = [];
            $type_guess_options = $type_guess->get_options();
            if (isset($type_guess_options['attr']) && isset($options['attr'])) {
                $attrs = ['attr' => array_merge($type_guess_options['attr'], $options['attr'])];
            }
            $options = array_merge($type_guess_options, $options, $attrs);
        }
        return $this->create_named_builder($property, $type, $data, $options);
    }
}