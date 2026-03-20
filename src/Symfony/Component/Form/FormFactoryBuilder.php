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

use Symfony\Component\Form\Extension\Core\Core_Extension;
/**
 * The default implementation of FormFactoryBuilderInterface.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class Form_Factory_Builder implements Form_Factory_Builder_Interface
{
    private Resolved_Form_Type_Factory_Interface $resolved_type_factory;
    /**
     * @var FormExtensionInterface[]
     */
    private array $extensions = [];
    /**
     * @var FormTypeInterface[]
     */
    private array $types = [];
    /**
     * @var FormTypeExtensionInterface[][]
     */
    private array $type_extensions = [];
    /**
     * @var FormTypeGuesserInterface[]
     */
    private array $type_guessers = [];
    public function __construct(private readonly bool $force_core_extension = false)
    {
    }
    public function set_resolved_type_factory(Resolved_Form_Type_Factory_Interface $resolved_type_factory): static
    {
        $this->resolved_type_factory = $resolved_type_factory;
        return $this;
    }
    public function add_extension(Form_Extension_Interface $extension): static
    {
        $this->extensions[] = $extension;
        return $this;
    }
    public function add_extensions(array $extensions): static
    {
        $this->extensions = array_merge($this->extensions, $extensions);
        return $this;
    }
    public function add_type(Form_Type_Interface $type): static
    {
        $this->types[] = $type;
        return $this;
    }
    public function add_types(array $types): static
    {
        foreach ($types as $type) {
            $this->types[] = $type;
        }
        return $this;
    }
    public function add_type_extension(Form_Type_Extension_Interface $type_extension): static
    {
        foreach ($type_extension::get_extended_types() as $extended_type) {
            $this->type_extensions[$extended_type][] = $type_extension;
        }
        return $this;
    }
    public function add_type_extensions(array $type_extensions): static
    {
        foreach ($type_extensions as $type_extension) {
            $this->add_type_extension($type_extension);
        }
        return $this;
    }
    public function add_type_guesser(Form_Type_Guesser_Interface $type_guesser): static
    {
        $this->type_guessers[] = $type_guesser;
        return $this;
    }
    public function add_type_guessers(array $type_guessers): static
    {
        $this->type_guessers = array_merge($this->type_guessers, $type_guessers);
        return $this;
    }
    public function get_form_factory(): Form_Factory_Interface
    {
        $extensions = $this->extensions;
        if ($this->force_core_extension) {
            $has_core_extension = false;
            foreach ($extensions as $extension) {
                if ($extension instanceof Core_Extension) {
                    $has_core_extension = true;
                    break;
                }
            }
            if (!$has_core_extension) {
                array_unshift($extensions, new Core_Extension());
            }
        }
        if (\count($this->types) > 0 || \count($this->type_extensions) > 0 || \count($this->type_guessers) > 0) {
            if (\count($this->type_guessers) > 1) {
                $type_guesser = new Form_Type_Guesser_Chain($this->type_guessers);
            } else {
                $type_guesser = $this->type_guessers[0] ?? null;
            }
            $extensions[] = new Preloaded_Extension($this->types, $this->type_extensions, $type_guesser);
        }
        $registry = new Form_Registry($extensions, $this->resolved_type_factory ?? new Resolved_Form_Type_Factory());
        return new Form_Factory($registry);
    }
}