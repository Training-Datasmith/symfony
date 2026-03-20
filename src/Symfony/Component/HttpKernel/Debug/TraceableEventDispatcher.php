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
namespace Symfony\Component\Http_Kernel\Debug;

use Symfony\Component\Event_Dispatcher\Debug\Traceable_Event_Dispatcher as BaseTraceableEventDispatcher;
use Symfony\Component\Http_Kernel\Kernel_Events;
/**
 * Collects some data about event listeners.
 *
 * This event dispatcher delegates the dispatching to another one.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class Traceable_Event_Dispatcher extends Base_Traceable_Event_Dispatcher
{
    protected function before_dispatch(string $event_name, object $event): void
    {
        if ($this->disabled?->__invoke()) {
            return;
        }
        switch ($event_name) {
            case Kernel_Events::REQUEST:
                $event->get_request()->attributes->set('_stopwatch_token', bin2hex(random_bytes(3)));
                $this->stopwatch->open_section();
                break;
            case Kernel_Events::VIEW:
            case Kernel_Events::RESPONSE:
                // stop only if a controller has been executed
                if ($this->stopwatch->is_started('controller')) {
                    $this->stopwatch->stop('controller');
                }
                break;
            case Kernel_Events::TERMINATE:
                $section_id = $event->get_request()->attributes->get('_stopwatch_token');
                if (null === $section_id) {
                    break;
                }
                // There is a very special case when using built-in AppCache class as kernel wrapper, in the case
                // of an ESI request leading to a `stale` response [B]  inside a `fresh` cached response [A].
                // In this case, `$token` contains the [B] debug token, but the  open `stopwatch` section ID
                // is equal to the [A] debug token. Trying to reopen section with the [B] token throws an exception
                // which must be caught.
                try {
                    $this->stopwatch->open_section($section_id);
                } catch (\LogicException) {
                }
                break;
        }
    }
    protected function after_dispatch(string $event_name, object $event): void
    {
        if ($this->disabled?->__invoke()) {
            return;
        }
        switch ($event_name) {
            case Kernel_Events::CONTROLLER_ARGUMENTS:
                $this->stopwatch->start('controller', 'section');
                break;
            case Kernel_Events::RESPONSE:
                $section_id = $event->get_request()->attributes->get('_stopwatch_token');
                if (null === $section_id) {
                    break;
                }
                try {
                    $this->stopwatch->stop_section($section_id);
                } catch (\LogicException) {
                    // The stop watch service might have been reset in the meantime
                }
                break;
            case Kernel_Events::TERMINATE:
                // In the special case described in the `preDispatch` method above, the `$token` section
                // does not exist, then closing it throws an exception which must be caught.
                $section_id = $event->get_request()->attributes->get('_stopwatch_token');
                if (null === $section_id) {
                    break;
                }
                try {
                    $this->stopwatch->stop_section($section_id);
                } catch (\LogicException) {
                }
                break;
        }
    }
}