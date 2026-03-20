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

use Symfony\Component\Form\Exception\InvalidArgumentException;
/**
 * A form extension with preloaded types, type extensions and type guessers.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class Preloaded_Extension implements Form_Extension_Interface
{
    private array $types = [];
    /**
     * Creates a new preloaded extension.
     *
     * @param FormTypeInterface[]            $types          The types that the extension should support
     * @param FormTypeExtensionInterface[][] $typeExtensions The type extensions that the extension should support
     */
    public function __construct(array $types, private array $type_extensions, private readonly ?Form_Type_Guesser_Interface $type_guesser = null)
    {
        foreach ($types as $type) {
            $this->types[$type::class] = $type;
        }
    }
    public function get_type(string $name): Form_Type_Interface
    {
        if (!isset($this->types[$name])) {
            throw new InvalidArgumentException(\sprintf('The type "%s" cannot be loaded by this extension.', $name));
        }
        return $this->types[$name];
    }
    public function has_type(string $name): bool
    {
        return isset($this->types[$name]);
    }
    public function get_type_extensions(string $name): array
    {
        return $this->type_extensions[$name] ?? [];
    }
    public function has_type_extensions(string $name): bool
    {
        return !empty($this->type_extensions[$name]);
    }
    public function get_type_guesser(): ?Form_Type_Guesser_Interface
    {
        return $this->type_guesser;
    }
}