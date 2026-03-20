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
namespace Symfony\Component\Http_Kernel\Event_Listener;

use Symfony\Component\Event_Dispatcher\Event_Subscriber_Interface;
use Symfony\Component\Http_Foundation\Request;
use Symfony\Component\Http_Foundation\Uri_Signer;
use Symfony\Component\Http_Kernel\Attribute\Is_Signature_Valid;
use Symfony\Component\Http_Kernel\Event\Controller_Arguments_Event;
use Symfony\Component\Http_Kernel\Event\Controller_Attribute_Event;
use Symfony\Component\Http_Kernel\Kernel_Events;
/**
 * Handles the IsSignatureValid attribute.
 *
 * @author Santiago San Martin <sanmartindev@gmail.com>
 */
class Is_Signature_Valid_Attribute_Listener implements Event_Subscriber_Interface
{
    public function __construct(private readonly Uri_Signer $uri_signer)
    {
    }
    public function on_kernel_controller_attribute(Controller_Attribute_Event $event): void
    {
        $kernel_event = $event->kernel_event;
        if (!$kernel_event instanceof Controller_Arguments_Event) {
            return;
        }
        $this->process_attribute($event->attribute, $kernel_event->get_request());
    }
    /**
     * @internal since Symfony 8.1, use onKernelControllerAttribute() instead
     */
    public function on_kernel_controller_arguments(Controller_Arguments_Event $event): void
    {
        $request = $event->get_request();
        foreach ($event->get_attributes(Is_Signature_Valid::class) as $attribute) {
            $this->process_attribute($attribute, $request);
        }
    }
    private function process_attribute(Is_Signature_Valid $attribute, Request $request): void
    {
        $methods = array_map(strtoupper(...), $attribute->methods);
        if ($methods && !\in_array($request->get_method(), $methods, true)) {
            return;
        }
        $this->uri_signer->verify($request);
    }
    public static function get_subscribed_events(): array
    {
        if (!class_exists(Controller_Attributes_Listener::class, false)) {
            return [Kernel_Events::CONTROLLER_ARGUMENTS => ['onKernelControllerArguments', 30]];
        }
        return [Kernel_Events::CONTROLLER_ARGUMENTS . '.' . Is_Signature_Valid::class => 'onKernelControllerAttribute'];
    }
}