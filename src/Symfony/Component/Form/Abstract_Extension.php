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
use Symfony\Component\Form\Exception\Unexpected_Type_Exception;
/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
abstract class Abstract_Extension implements Form_Extension_Interface
{
    /**
     * The types provided by this extension.
     *
     * @var FormTypeInterface[]
     */
    private array $types;
    /**
     * The type extensions provided by this extension.
     *
     * @var FormTypeExtensionInterface[][]
     */
    private array $type_extensions;
    /**
     * The type guesser provided by this extension.
     */
    private ?Form_Type_Guesser_Interface $type_guesser = null;
    /**
     * Whether the type guesser has been loaded.
     */
    private bool $type_guesser_loaded = false;
    public function get_type(string $name): Form_Type_Interface
    {
        if (!isset($this->types)) {
            $this->init_types();
        }
        if (!isset($this->types[$name])) {
            throw new InvalidArgumentException(\sprintf('The type "%s" cannot be loaded by this extension.', $name));
        }
        return $this->types[$name];
    }
    public function has_type(string $name): bool
    {
        if (!isset($this->types)) {
            $this->init_types();
        }
        return isset($this->types[$name]);
    }
    public function get_type_extensions(string $name): array
    {
        if (!isset($this->type_extensions)) {
            $this->init_type_extensions();
        }
        return $this->type_extensions[$name] ?? [];
    }
    public function has_type_extensions(string $name): bool
    {
        if (!isset($this->type_extensions)) {
            $this->init_type_extensions();
        }
        return isset($this->type_extensions[$name]) && \count($this->type_extensions[$name]) > 0;
    }
    public function get_type_guesser(): ?Form_Type_Guesser_Interface
    {
        if (!$this->type_guesser_loaded) {
            $this->init_type_guesser();
        }
        return $this->type_guesser;
    }
    /**
     * Registers the types.
     *
     * @return FormTypeInterface[]
     */
    protected function load_types(): array
    {
        return [];
    }
    /**
     * Registers the type extensions.
     *
     * @return FormTypeExtensionInterface[]
     */
    protected function load_type_extensions(): array
    {
        return [];
    }
    /**
     * Registers the type guesser.
     */
    protected function load_type_guesser(): ?Form_Type_Guesser_Interface
    {
        return null;
    }
    /**
     * Initializes the types.
     *
     * @throws UnexpectedTypeException if any registered type is not an instance of FormTypeInterface
     */
    private function init_types(): void
    {
        $this->types = [];
        foreach ($this->load_types() as $type) {
            if (!$type instanceof Form_Type_Interface) {
                throw new Unexpected_Type_Exception($type, Form_Type_Interface::class);
            }
            $this->types[$type::class] = $type;
        }
    }
    /**
     * Initializes the type extensions.
     *
     * @throws UnexpectedTypeException if any registered type extension is not
     *                                 an instance of FormTypeExtensionInterface
     */
    private function init_type_extensions(): void
    {
        $this->type_extensions = [];
        foreach ($this->load_type_extensions() as $extension) {
            if (!$extension instanceof Form_Type_Extension_Interface) {
                throw new Unexpected_Type_Exception($extension, Form_Type_Extension_Interface::class);
            }
            foreach ($extension::get_extended_types() as $extended_type) {
                $this->type_extensions[$extended_type][] = $extension;
            }
        }
    }
    /**
     * Initializes the type guesser.
     *
     * @throws UnexpectedTypeException if the type guesser is not an instance of FormTypeGuesserInterface
     */
    private function init_type_guesser(): void
    {
        $this->type_guesser_loaded = true;
        $this->type_guesser = $this->load_type_guesser();
        if (null !== $this->type_guesser && !$this->type_guesser instanceof Form_Type_Guesser_Interface) {
            throw new Unexpected_Type_Exception($this->type_guesser, Form_Type_Guesser_Interface::class);
        }
    }
}