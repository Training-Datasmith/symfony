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
use Symfony\Component\Form\Flow\Form_Flow_Builder_Interface;
use Symfony\Component\Form\Flow\Form_Flow_Interface;
use Symfony\Component\Form\Flow\Form_Flow_Type_Interface;
use Symfony\Component\Options_Resolver\Exception\Invalid_Options_Exception;
/**
 * Allows creating a form based on a name, a class or a property.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
interface Form_Factory_Interface
{
    /**
     * Returns a form.
     *
     * @see createBuilder()
     *
     * @param mixed $data The initial data
     *
     * @return ($type is class-string<FormFlowTypeInterface> ? FormFlowInterface : FormInterface)
     *
     * @throws InvalidOptionsException if any given option is not applicable to the given type
     */
    public function create(string $type = Form_Type::class, mixed $data = null, array $options = []): Form_Interface;
    /**
     * Returns a form.
     *
     * @see createNamedBuilder()
     *
     * @param mixed $data The initial data
     *
     * @return ($type is class-string<FormFlowTypeInterface> ? FormFlowInterface : FormInterface)
     *
     * @throws InvalidOptionsException if any given option is not applicable to the given type
     */
    public function create_named(string $name, string $type = Form_Type::class, mixed $data = null, array $options = []): Form_Interface;
    /**
     * Returns a form for a property of a class.
     *
     * @see createBuilderForProperty()
     *
     * @param string $class    The fully qualified class name
     * @param string $property The name of the property to guess for
     * @param mixed  $data     The initial data
     *
     * @throws InvalidOptionsException if any given option is not applicable to the form type
     */
    public function create_for_property(string $class, string $property, mixed $data = null, array $options = []): Form_Interface;
    /**
     * Returns a form builder.
     *
     * @param mixed $data The initial data
     *
     * @return ($type is class-string<FormFlowTypeInterface> ? FormFlowBuilderInterface : FormBuilderInterface)
     *
     * @throws InvalidOptionsException if any given option is not applicable to the given type
     */
    public function create_builder(string $type = Form_Type::class, mixed $data = null, array $options = []): Form_Builder_Interface;
    /**
     * Returns a form builder.
     *
     * @param mixed $data The initial data
     *
     * @return ($type is class-string<FormFlowTypeInterface> ? FormFlowBuilderInterface : FormBuilderInterface)
     *
     * @throws InvalidOptionsException if any given option is not applicable to the given type
     */
    public function create_named_builder(string $name, string $type = Form_Type::class, mixed $data = null, array $options = []): Form_Builder_Interface;
    /**
     * Returns a form builder for a property of a class.
     *
     * If any of the 'required' and type options can be guessed,
     * and are not provided in the options argument, the guessed value is used.
     *
     * @param string $class    The fully qualified class name
     * @param string $property The name of the property to guess for
     * @param mixed  $data     The initial data
     *
     * @throws InvalidOptionsException if any given option is not applicable to the form type
     */
    public function create_builder_for_property(string $class, string $property, mixed $data = null, array $options = []): Form_Builder_Interface;
}