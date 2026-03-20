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
 * Interface for extensions which provide types, type extensions and a guesser.
 */
interface Form_Extension_Interface
{
    /**
     * Returns a type by name.
     *
     * @param string $name The name of the type
     *
     * @throws Exception\InvalidArgumentException if the given type is not supported by this extension
     */
    public function get_type(string $name): Form_Type_Interface;
    /**
     * Returns whether the given type is supported.
     *
     * @param string $name The name of the type
     */
    public function has_type(string $name): bool;
    /**
     * Returns the extensions for the given type.
     *
     * @param string $name The name of the type
     *
     * @return FormTypeExtensionInterface[]
     */
    public function get_type_extensions(string $name): array;
    /**
     * Returns whether this extension provides type extensions for the given type.
     *
     * @param string $name The name of the type
     */
    public function has_type_extensions(string $name): bool;
    /**
     * Returns the type guesser provided by this extension.
     */
    public function get_type_guesser(): ?Form_Type_Guesser_Interface;
}