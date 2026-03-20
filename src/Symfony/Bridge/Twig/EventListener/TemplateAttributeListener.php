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
namespace Symfony\Bridge\Twig\Event_Listener;

use Symfony\Bridge\Twig\Attribute\Template;
use Symfony\Component\Event_Dispatcher\Event_Subscriber_Interface;
use Symfony\Component\Form\Form_Interface;
use Symfony\Component\Http_Foundation\Response;
use Symfony\Component\Http_Foundation\Streamed_Response;
use Symfony\Component\Http_Kernel\Event\Controller_Arguments_Event;
use Symfony\Component\Http_Kernel\Event\Controller_Arguments_Metadata;
use Symfony\Component\Http_Kernel\Event\Controller_Attribute_Event;
use Symfony\Component\Http_Kernel\Event\View_Event;
use Symfony\Component\Http_Kernel\Event_Listener\Controller_Attributes_Listener;
use Symfony\Component\Http_Kernel\Kernel_Events;
use Twig\Environment;
class Template_Attribute_Listener implements Event_Subscriber_Interface
{
    public function __construct(private readonly Environment $twig)
    {
    }
    public function on_kernel_controller_attribute(Controller_Attribute_Event $event): void
    {
        if (!$event->kernel_event instanceof View_Event) {
            return;
        }
        if (!$event->kernel_event->get_request()->attributes->has('_template')) {
            $event->kernel_event->get_request()->attributes->set('_template', $event->attribute);
        }
        $this->on_kernel_view($event->kernel_event);
    }
    /**
     * @internal since Symfony 8.1, use onKernelControllerAttribute() instead
     */
    public function on_kernel_view(View_Event $event): void
    {
        $parameters = $event->get_controller_result();
        if (!\is_array($parameters ?? [])) {
            return;
        }
        $attribute = $event->get_request()->attributes->get('_template');
        if (!$attribute instanceof Template && !$attribute = $event->{class_exists(Controller_Arguments_Metadata::class, false) ? 'controllerMetadata' : 'controllerArgumentsEvent'}?->get_attributes(Template::class)[0] ?? null) {
            return;
        }
        $parameters ??= $this->resolve_parameters($event->{class_exists(Controller_Arguments_Metadata::class, false) ? 'controllerMetadata' : 'controllerArgumentsEvent'}, $attribute->vars);
        $status = 200;
        foreach ($parameters as $k => $v) {
            if (!$v instanceof Form_Interface) {
                continue;
            }
            if ($v->is_submitted() && !$v->is_valid()) {
                $status = 422;
            }
            $parameters[$k] = $v->create_view();
        }
        $event->set_response($attribute->stream ? new Streamed_Response(null !== $attribute->block ? fn() => $this->twig->load($attribute->template)->display_block($attribute->block, $parameters) : fn() => $this->twig->display($attribute->template, $parameters), $status) : new Response(null !== $attribute->block ? $this->twig->load($attribute->template)->render_block($attribute->block, $parameters) : $this->twig->render($attribute->template, $parameters), $status));
    }
    public static function get_subscribed_events(): array
    {
        if (!class_exists(Controller_Attributes_Listener::class, false)) {
            return [Kernel_Events::VIEW => ['onKernelView', -128]];
        }
        return [Kernel_Events::VIEW . '.' . Template::class => 'onKernelControllerAttribute'];
    }
    private function resolve_parameters(Controller_Arguments_Metadata|Controller_Arguments_Event $controller_metadata, ?array $vars): array
    {
        if ([] === $vars) {
            return [];
        }
        $parameters = $controller_metadata->get_named_arguments();
        if (null !== $vars) {
            return array_intersect_key($parameters, array_flip($vars));
        }
        return $parameters;
    }
}