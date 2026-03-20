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
 * A builder for FormFactoryInterface objects.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
interface Form_Factory_Builder_Interface
{
    /**
     * Sets the factory for creating ResolvedFormTypeInterface instances.
     *
     * @return $this
     */
    public function set_resolved_type_factory(Resolved_Form_Type_Factory_Interface $resolved_type_factory): static;
    /**
     * Adds an extension to be loaded by the factory.
     *
     * @return $this
     */
    public function add_extension(Form_Extension_Interface $extension): static;
    /**
     * Adds a list of extensions to be loaded by the factory.
     *
     * @param FormExtensionInterface[] $extensions The extensions
     *
     * @return $this
     */
    public function add_extensions(array $extensions): static;
    /**
     * Adds a form type to the factory.
     *
     * @return $this
     */
    public function add_type(Form_Type_Interface $type): static;
    /**
     * Adds a list of form types to the factory.
     *
     * @param FormTypeInterface[] $types The form types
     *
     * @return $this
     */
    public function add_types(array $types): static;
    /**
     * Adds a form type extension to the factory.
     *
     * @return $this
     */
    public function add_type_extension(Form_Type_Extension_Interface $type_extension): static;
    /**
     * Adds a list of form type extensions to the factory.
     *
     * @param FormTypeExtensionInterface[] $typeExtensions The form type extensions
     *
     * @return $this
     */
    public function add_type_extensions(array $type_extensions): static;
    /**
     * Adds a type guesser to the factory.
     *
     * @return $this
     */
    public function add_type_guesser(Form_Type_Guesser_Interface $type_guesser): static;
    /**
     * Adds a list of type guessers to the factory.
     *
     * @param FormTypeGuesserInterface[] $typeGuessers The type guessers
     *
     * @return $this
     */
    public function add_type_guessers(array $type_guessers): static;
    /**
     * Builds and returns the factory.
     */
    public function get_form_factory(): Form_Factory_Interface;
}