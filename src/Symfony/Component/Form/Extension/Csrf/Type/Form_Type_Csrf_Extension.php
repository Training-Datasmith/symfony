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
namespace Symfony\Component\Form\Extension\Csrf\Type;

use Symfony\Component\Form\Abstract_Type_Extension;
use Symfony\Component\Form\Extension\Core\Type\Form_Type;
use Symfony\Component\Form\Extension\Core\Type\Hidden_Type;
use Symfony\Component\Form\Extension\Csrf\Event_Listener\Csrf_Validation_Listener;
use Symfony\Component\Form\Form_Builder_Interface;
use Symfony\Component\Form\Form_Interface;
use Symfony\Component\Form\Form_View;
use Symfony\Component\Form\Util\Server_Params;
use Symfony\Component\Options_Resolver\Options;
use Symfony\Component\Options_Resolver\Options_Resolver;
use Symfony\Component\Security\Csrf\Csrf_Token_Manager_Interface;
use Symfony\Contracts\Translation\Translator_Interface;
/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class Form_Type_Csrf_Extension extends Abstract_Type_Extension
{
    public function __construct(private readonly Csrf_Token_Manager_Interface $default_token_manager, private readonly bool $default_enabled = true, private readonly string $default_field_name = '_token', private readonly ?Translator_Interface $translator = null, private readonly ?string $translation_domain = null, private readonly ?Server_Params $server_params = null, private readonly array $field_attr = [], private string|array|null $default_token_id = null)
    {
    }
    /**
     * Adds a CSRF field to the form when the CSRF protection is enabled.
     */
    public function build_form(Form_Builder_Interface $builder, array $options): void
    {
        if (!$options['csrf_protection']) {
            return;
        }
        $csrf_token_id = ($options['csrf_token_id'] ?: $this->default_token_id[$builder->get_type()->get_inner_type()::class] ?? $builder->get_name()) ?: $builder->get_type()->get_inner_type()::class;
        $builder->set_attribute('csrf_token_id', $csrf_token_id);
        $builder->add_event_subscriber(new Csrf_Validation_Listener($options['csrf_field_name'], $options['csrf_token_manager'], $csrf_token_id, $options['csrf_message'], $this->translator, $this->translation_domain, $this->server_params));
    }
    /**
     * Adds a CSRF field to the root form view.
     */
    public function finish_view(Form_View $view, Form_Interface $form, array $options): void
    {
        if ($options['csrf_protection'] && !$view->parent && $options['compound']) {
            $factory = $form->get_config()->get_form_factory();
            $token_id = $form->get_config()->get_attribute('csrf_token_id');
            $data = (string) $options['csrf_token_manager']->get_token($token_id);
            $csrf_form = $factory->create_named($options['csrf_field_name'], Hidden_Type::class, $data, ['block_prefix' => 'csrf_token', 'mapped' => false, 'attr' => $this->field_attr]);
            $view->children[$options['csrf_field_name']] = $csrf_form->create_view($view);
        }
    }
    public function configure_options(Options_Resolver $resolver): void
    {
        if (\is_string($default_token_id = $this->default_token_id) && $default_token_id) {
            $default_token_manager = $this->default_token_manager;
            $default_token_id = static fn(Options $options) => $options['csrf_token_manager'] === $default_token_manager ? $default_token_id : null;
        } else {
            $default_token_id = null;
        }
        $resolver->set_defaults(['csrf_protection' => $this->default_enabled, 'csrf_field_name' => $this->default_field_name, 'csrf_message' => 'The CSRF token is invalid. Please try to resubmit the form.', 'csrf_token_manager' => $this->default_token_manager, 'csrf_token_id' => $default_token_id]);
        $resolver->set_allowed_types('csrf_protection', 'bool');
        $resolver->set_allowed_types('csrf_field_name', 'string');
        $resolver->set_allowed_types('csrf_message', 'string');
        $resolver->set_allowed_types('csrf_token_manager', Csrf_Token_Manager_Interface::class);
        $resolver->set_allowed_types('csrf_token_id', ['null', 'string']);
    }
    public static function get_extended_types(): iterable
    {
        return [Form_Type::class];
    }
}