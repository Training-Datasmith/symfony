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
namespace Symfony\Component\Form\Extension\Validator;

use Symfony\Component\Form\Abstract_Extension;
use Symfony\Component\Form\Extension\Validator\Constraints\Form;
use Symfony\Component\Form\Extension\Validator\Violation_Mapper\Violation_Mapper_Interface;
use Symfony\Component\Form\Form_Renderer_Interface;
use Symfony\Component\Form\Form_Type_Guesser_Interface;
use Symfony\Component\Validator\Constraints\Traverse;
use Symfony\Component\Validator\Mapping\Class_Metadata;
use Symfony\Component\Validator\Validator\Validator_Interface;
use Symfony\Contracts\Translation\Translator_Interface;
/**
 * Extension supporting the Symfony Validator component in forms.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class Validator_Extension extends Abstract_Extension
{
    private readonly ?Violation_Mapper_Interface $violation_mapper;
    public function __construct(private readonly Validator_Interface $validator, bool|Violation_Mapper_Interface|null $violation_mapper = null, private readonly ?Form_Renderer_Interface $form_renderer = null, private readonly ?Translator_Interface $translator = null)
    {
        if (\is_bool($violation_mapper)) {
            trigger_deprecation('symfony/form', '8.1', \sprintf('Passing a boolean as a second argument of "%s"\'s constructor is deprecated; pass a "%s" instead.', self::class, Violation_Mapper_Interface::class));
            $violation_mapper = null;
        }
        $this->violation_mapper = $violation_mapper;
        /** @var ClassMetadata $metadata */
        $metadata = $validator->get_metadata_for(\Symfony\Component\Form\Form::class);
        // Register the form constraints in the validator programmatically.
        // This functionality is required when using the Form component without
        // the DIC, where the XML file is loaded automatically. Thus the following
        // code must be kept synchronized with validation.xml
        $metadata->add_constraint(new Form());
        $metadata->add_constraint(new Traverse(false));
    }
    public function load_type_guesser(): ?Form_Type_Guesser_Interface
    {
        return new Validator_Type_Guesser($this->validator);
    }
    protected function load_type_extensions(): array
    {
        return [new Type\Form_Type_Validator_Extension($this->validator, $this->violation_mapper, $this->form_renderer, $this->translator), new Type\Repeated_Type_Validator_Extension(), new Type\Submit_Type_Validator_Extension()];
    }
}