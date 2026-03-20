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

use Symfony\Component\Form\Exception\Exception_Interface;
use Symfony\Component\Form\Exception\InvalidArgumentException;
use Symfony\Component\Form\Exception\LogicException;
use Symfony\Component\Form\Exception\Unexpected_Type_Exception;
/**
 * The central registry of the Form component.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class Form_Registry implements Form_Registry_Interface
{
    /**
     * @var FormExtensionInterface[]
     */
    private array $extensions = [];
    /**
     * @var ResolvedFormTypeInterface[]
     */
    private array $types = [];
    private Form_Type_Guesser_Interface|false|null $guesser = false;
    private array $checked_types = [];
    /**
     * @param FormExtensionInterface[] $extensions
     *
     * @throws UnexpectedTypeException if any extension does not implement FormExtensionInterface
     */
    public function __construct(array $extensions, private readonly Resolved_Form_Type_Factory_Interface $resolved_type_factory)
    {
        foreach ($extensions as $extension) {
            if (!$extension instanceof Form_Extension_Interface) {
                throw new Unexpected_Type_Exception($extension, Form_Extension_Interface::class);
            }
        }
        $this->extensions = $extensions;
    }
    public function get_type(string $name): Resolved_Form_Type_Interface
    {
        if (!isset($this->types[$name])) {
            $type = null;
            foreach ($this->extensions as $extension) {
                if ($extension->has_type($name)) {
                    $type = $extension->get_type($name);
                    break;
                }
            }
            if (!$type) {
                // Support fully-qualified class names
                if (!class_exists($name)) {
                    throw new InvalidArgumentException(\sprintf('Could not load type "%s": class does not exist.', $name));
                }
                if (!is_subclass_of($name, Form_Type_Interface::class)) {
                    throw new InvalidArgumentException(\sprintf('Could not load type "%s": class does not implement "Symfony\Component\Form\FormTypeInterface".', $name));
                }
                $type = new $name();
            }
            $this->types[$name] = $this->resolve_type($type);
        }
        return $this->types[$name];
    }
    /**
     * Wraps a type into a ResolvedFormTypeInterface implementation and connects it with its parent type.
     */
    private function resolve_type(Form_Type_Interface $type): Resolved_Form_Type_Interface
    {
        $parent_type = $type->get_parent();
        $fqcn = $type::class;
        if (isset($this->checked_types[$fqcn])) {
            $types = implode(' > ', array_merge(array_keys($this->checked_types), [$fqcn]));
            throw new LogicException(\sprintf('Circular reference detected for form type "%s" (%s).', $fqcn, $types));
        }
        $this->checked_types[$fqcn] = true;
        $type_extensions = [];
        try {
            foreach ($this->extensions as $extension) {
                $type_extensions[] = $extension->get_type_extensions($fqcn);
            }
            return $this->resolved_type_factory->create_resolved_type($type, array_merge([], ...$type_extensions), $parent_type ? $this->get_type($parent_type) : null);
        } finally {
            unset($this->checked_types[$fqcn]);
        }
    }
    public function has_type(string $name): bool
    {
        if (isset($this->types[$name])) {
            return true;
        }
        try {
            $this->get_type($name);
        } catch (Exception_Interface) {
            return false;
        }
        return true;
    }
    public function get_type_guesser(): ?Form_Type_Guesser_Interface
    {
        if (false === $this->guesser) {
            $guessers = [];
            foreach ($this->extensions as $extension) {
                $guesser = $extension->get_type_guesser();
                if ($guesser) {
                    $guessers[] = $guesser;
                }
            }
            $this->guesser = $guessers ? new Form_Type_Guesser_Chain($guessers) : null;
        }
        return $this->guesser;
    }
    public function get_extensions(): array
    {
        return $this->extensions;
    }
}