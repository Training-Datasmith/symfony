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
namespace Symfony\Component\Form\Extension\Csrf\Event_Listener;

use Symfony\Component\Event_Dispatcher\Event_Subscriber_Interface;
use Symfony\Component\Form\Form_Error;
use Symfony\Component\Form\Form_Event;
use Symfony\Component\Form\Form_Events;
use Symfony\Component\Form\Util\Server_Params;
use Symfony\Component\Security\Csrf\Csrf_Token;
use Symfony\Component\Security\Csrf\Csrf_Token_Manager_Interface;
use Symfony\Contracts\Translation\Translator_Interface;
/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class Csrf_Validation_Listener implements Event_Subscriber_Interface
{
    public static function get_subscribed_events(): array
    {
        return [Form_Events::PRE_SUBMIT => 'preSubmit'];
    }
    public function __construct(private readonly string $field_name, private readonly Csrf_Token_Manager_Interface $token_manager, private readonly string $token_id, private readonly string $error_message, private readonly ?Translator_Interface $translator = null, private readonly ?string $translation_domain = null, private readonly ?Server_Params $server_params = new Server_Params())
    {
    }
    public function pre_submit(Form_Event $event): void
    {
        $form = $event->get_form();
        $post_request_size_exceeded = 'POST' === $form->get_config()->get_method() && $this->server_params->has_post_max_size_been_exceeded();
        if ($form->is_root() && $form->get_config()->get_option('compound') && !$post_request_size_exceeded) {
            $data = $event->get_data();
            $csrf_value = \is_string($data[$this->field_name] ?? null) ? $data[$this->field_name] : null;
            $csrf_token = new Csrf_Token($this->token_id, $csrf_value);
            if (null === $csrf_value || !$this->token_manager->is_token_valid($csrf_token)) {
                $error_message = $this->error_message;
                if (null !== $this->translator) {
                    $error_message = $this->translator->trans($error_message, [], $this->translation_domain);
                }
                $form->add_error(new Form_Error($error_message, $error_message, [], null, $csrf_token));
            }
            if (\is_array($data)) {
                unset($data[$this->field_name]);
                $event->set_data($data);
            }
        }
    }
}