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
use Symfony\Component\Http_Foundation\Request_Stack;
use Symfony\Component\Http_Kernel\Event\Finish_Request_Event;
use Symfony\Component\Http_Kernel\Event\Request_Event;
use Symfony\Component\Http_Kernel\Kernel_Events;
use Symfony\Contracts\Translation\Locale_Aware_Interface;
/**
 * Pass the current locale to the provided services.
 *
 * @author Pierre Bobiet <pierrebobiet@gmail.com>
 */
class Locale_Aware_Listener implements Event_Subscriber_Interface
{
    /**
     * @param iterable<mixed, LocaleAwareInterface> $localeAwareServices
     */
    public function __construct(private readonly iterable $locale_aware_services, private readonly Request_Stack $request_stack)
    {
    }
    public function on_kernel_request(Request_Event $event): void
    {
        $this->set_locale($event->get_request()->get_locale(), $event->get_request()->get_default_locale());
    }
    public function on_kernel_finish_request(Finish_Request_Event $event): void
    {
        if (null === $parent_request = $this->request_stack->get_parent_request()) {
            foreach ($this->locale_aware_services as $service) {
                $service->set_locale($event->get_request()->get_default_locale());
            }
            return;
        }
        $this->set_locale($parent_request->get_locale(), $parent_request->get_default_locale());
    }
    public static function get_subscribed_events(): array
    {
        return [
            // must be registered after the Locale listener
            Kernel_Events::REQUEST => [['onKernelRequest', 15]],
            Kernel_Events::FINISH_REQUEST => [['onKernelFinishRequest', -15]],
        ];
    }
    private function set_locale(string $locale, string $default_locale): void
    {
        foreach ($this->locale_aware_services as $service) {
            try {
                $service->set_locale($locale);
            } catch (\InvalidArgumentException) {
                $service->set_locale($default_locale);
            }
        }
    }
}