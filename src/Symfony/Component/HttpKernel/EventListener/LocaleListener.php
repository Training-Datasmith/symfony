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
use Symfony\Component\Http_Foundation\Request_Stack;
use Symfony\Component\Http_Kernel\Event\Finish_Request_Event;
use Symfony\Component\Http_Kernel\Event\Kernel_Event;
use Symfony\Component\Http_Kernel\Event\Request_Event;
use Symfony\Component\Http_Kernel\Kernel_Events;
use Symfony\Component\Routing\Request_Context_Aware_Interface;
/**
 * Initializes the locale based on the current request.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @final
 */
class Locale_Listener implements Event_Subscriber_Interface
{
    public function __construct(private readonly Request_Stack $request_stack, private readonly string $default_locale = 'en', private readonly ?Request_Context_Aware_Interface $router = null, private readonly bool $use_accept_language_header = false, private array $enabled_locales = [])
    {
        $this->enabled_locales = $enabled_locales ? array_values(array_unique(array_merge([$default_locale], array_filter($enabled_locales)))) : [];
    }
    public function set_default_locale(Kernel_Event $event): void
    {
        $event->get_request()->set_default_locale($this->default_locale);
    }
    public function on_kernel_request(Request_Event $event): void
    {
        $request = $event->get_request();
        $this->set_locale($request);
        $this->set_router_context($request);
    }
    public function on_kernel_finish_request(Finish_Request_Event $event): void
    {
        if (null !== $parent_request = $this->request_stack->get_parent_request()) {
            $this->set_router_context($parent_request);
        }
    }
    private function set_locale(Request $request): void
    {
        if ($locale = $request->attributes->get('_locale')) {
            $request->set_locale($locale);
        } elseif ($this->use_accept_language_header) {
            if ($request->get_languages() && $preferred_language = $request->get_preferred_language($this->enabled_locales)) {
                $request->set_locale($preferred_language);
            }
            $request->attributes->set('_vary_by_language', true);
        }
    }
    private function set_router_context(Request $request): void
    {
        $this->router?->get_context()->set_parameter('_locale', $request->get_locale());
    }
    public static function get_subscribed_events(): array
    {
        return [Kernel_Events::REQUEST => [
            ['setDefaultLocale', 100],
            // must be registered after the Router to have access to the _locale
            ['onKernelRequest', 16],
        ], Kernel_Events::FINISH_REQUEST => [['onKernelFinishRequest', 0]]];
    }
}