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

/**
 * Entry point of the Form component.
 *
 * Use this class to conveniently create new form factories:
 *
 *     use Symfony\Component\Form\Forms;
 *
 *     $formFactory = Forms::createFormFactory();
 *
 *     $form = $formFactory->createBuilder()
 *         ->add('firstName', 'Symfony\Component\Form\Extension\Core\Type\TextType')
 *         ->add('lastName', 'Symfony\Component\Form\Extension\Core\Type\TextType')
 *         ->add('age', 'Symfony\Component\Form\Extension\Core\Type\IntegerType')
 *         ->add('color', 'Symfony\Component\Form\Extension\Core\Type\ChoiceType', [
 *             'choices' => ['Red' => 'r', 'Blue' => 'b'],
 *         ])
 *         ->getForm();
 *
 * You can also add custom extensions to the form factory:
 *
 *     $formFactory = Forms::createFormFactoryBuilder()
 *         ->addExtension(new AcmeExtension())
 *         ->getFormFactory();
 *
 * If you create custom form types or type extensions, it is
 * generally recommended to create your own extensions that lazily
 * load these types and type extensions. In projects where performance
 * does not matter that much, you can also pass them directly to the
 * form factory:
 *
 *     $formFactory = Forms::createFormFactoryBuilder()
 *         ->addType(new PersonType())
 *         ->addType(new PhoneNumberType())
 *         ->addTypeExtension(new FormTypeHelpTextExtension())
 *         ->getFormFactory();
 *
 * Support for the Validator component is provided by ValidatorExtension.
 * This extension needs a validator object to function properly:
 *
 *     use Symfony\Component\Validator\Validation;
 *     use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
 *
 *     $validator = Validation::createValidator();
 *     $formFactory = Forms::createFormFactoryBuilder()
 *         ->addExtension(new ValidatorExtension($validator))
 *         ->getFormFactory();
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
final class Forms
{
    /**
     * Creates a form factory with the default configuration.
     */
    public static function create_form_factory(): Form_Factory_Interface
    {
        return self::create_form_factory_builder()->get_form_factory();
    }
    /**
     * Creates a form factory builder with the default configuration.
     */
    public static function create_form_factory_builder(): Form_Factory_Builder_Interface
    {
        return new Form_Factory_Builder(true);
    }
    /**
     * This class cannot be instantiated.
     */
    private function __construct()
    {
    }
}