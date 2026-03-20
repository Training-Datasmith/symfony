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
namespace Symfony\Component\Form\Extension\Validator\Type;

use Symfony\Component\Form\Extension\Core\Type\Form_Type;
use Symfony\Component\Form\Extension\Validator\Event_Listener\Validation_Listener;
use Symfony\Component\Form\Extension\Validator\Violation_Mapper\Violation_Mapper;
use Symfony\Component\Form\Extension\Validator\Violation_Mapper\Violation_Mapper_Interface;
use Symfony\Component\Form\Form_Builder_Interface;
use Symfony\Component\Form\Form_Renderer_Interface;
use Symfony\Component\Options_Resolver\Options;
use Symfony\Component\Options_Resolver\Options_Resolver;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Validator\Validator_Interface;
use Symfony\Contracts\Translation\Translator_Interface;
/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class Form_Type_Validator_Extension extends Base_Validator_Extension
{
    private readonly Violation_Mapper_Interface $violation_mapper;
    public function __construct(private readonly Validator_Interface $validator, bool|Violation_Mapper_Interface|null $violation_mapper = null, ?Form_Renderer_Interface $form_renderer = null, ?Translator_Interface $translator = null)
    {
        if (\is_bool($violation_mapper)) {
            trigger_deprecation('symfony/form', '8.1', \sprintf('Passing a boolean as a second argument of "%s"\'s constructor is deprecated; pass a "%s" instead.', self::class, Violation_Mapper_Interface::class));
            $violation_mapper = null;
        }
        $this->violation_mapper = $violation_mapper ?? new Violation_Mapper($form_renderer, $translator);
    }
    public function build_form(Form_Builder_Interface $builder, array $options): void
    {
        $builder->add_event_subscriber(new Validation_Listener($this->validator, $this->violation_mapper));
    }
    public function configure_options(Options_Resolver $resolver): void
    {
        parent::configure_options($resolver);
        // Constraint should always be converted to an array
        $constraints_normalizer = static fn(Options $options, $constraints): array => \is_object($constraints) ? [$constraints] : (array) $constraints;
        $resolver->set_defaults(['error_mapping' => [], 'constraints' => [], 'invalid_message' => 'This value is not valid.', 'invalid_message_parameters' => [], 'allow_extra_fields' => false, 'extra_fields_message' => 'This form should not contain extra fields.']);
        $resolver->set_allowed_types('constraints', [Constraint::class, Constraint::class . '[]']);
        $resolver->set_normalizer('constraints', $constraints_normalizer);
    }
    public static function get_extended_types(): iterable
    {
        return [Form_Type::class];
    }
}