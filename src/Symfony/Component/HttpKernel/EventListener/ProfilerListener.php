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
use Symfony\Component\Http_Foundation\Request_Matcher_Interface;
use Symfony\Component\Http_Foundation\Request_Stack;
use Symfony\Component\Http_Foundation\Session\Session;
use Symfony\Component\Http_Kernel\Event\Exception_Event;
use Symfony\Component\Http_Kernel\Event\Response_Event;
use Symfony\Component\Http_Kernel\Event\Terminate_Event;
use Symfony\Component\Http_Kernel\Kernel_Events;
use Symfony\Component\Http_Kernel\Profiler\Profile;
use Symfony\Component\Http_Kernel\Profiler\Profiler;
/**
 * ProfilerListener collects data for the current request by listening to the kernel events.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @final
 */
class Profiler_Listener implements Event_Subscriber_Interface
{
    private ?\Throwable $exception = null;
    /** @var \SplObjectStorage<Request, Profile> */
    private \Spl_Object_Storage $profiles;
    /** @var \SplObjectStorage<Request, Request|null> */
    private \Spl_Object_Storage $parents;
    /**
     * @param bool $onlyException    True if the profiler only collects data when an exception occurs, false otherwise
     * @param bool $onlyMainRequests True if the profiler only collects data when the request is the main request, false otherwise
     */
    public function __construct(private readonly Profiler $profiler, private readonly Request_Stack $request_stack, private readonly ?Request_Matcher_Interface $matcher = null, private readonly bool $only_exception = false, private readonly bool $only_main_requests = false, private readonly ?string $collect_parameter = null)
    {
        $this->profiles = new \Spl_Object_Storage();
        $this->parents = new \Spl_Object_Storage();
    }
    /**
     * Handles the onKernelException event.
     */
    public function on_kernel_exception(Exception_Event $event): void
    {
        if ($this->only_main_requests && !$event->is_main_request()) {
            return;
        }
        $this->exception = $event->get_throwable();
    }
    /**
     * Handles the onKernelResponse event.
     */
    public function on_kernel_response(Response_Event $event): void
    {
        if ($this->only_main_requests && !$event->is_main_request()) {
            return;
        }
        if ($this->only_exception && null === $this->exception) {
            return;
        }
        $request = $event->get_request();
        if (null !== $this->collect_parameter && null !== $collect_parameter_value = $request->attributes->get($this->collect_parameter) ?? $request->query->get($this->collect_parameter) ?? $request->request->get($this->collect_parameter)) {
            filter_var($collect_parameter_value, \FILTER_VALIDATE_BOOL) ? $this->profiler->enable() : $this->profiler->disable();
        }
        $exception = $this->exception;
        $this->exception = null;
        if (null !== $this->matcher && !$this->matcher->matches($request)) {
            return;
        }
        $session = !$request->attributes->get_boolean('_stateless') && $request->has_previous_session() ? $request->get_session() : null;
        if ($session instanceof Session) {
            $usage_index_value = $usage_index_reference =& $session->get_usage_index();
            $usage_index_reference = \PHP_INT_MIN;
        }
        try {
            if (!$profile = $this->profiler->collect($request, $event->get_response(), $exception)) {
                return;
            }
        } finally {
            if ($session instanceof Session) {
                $usage_index_reference = $usage_index_value;
            }
        }
        $this->profiles[$request] = $profile;
        $this->parents[$request] = $this->request_stack->get_parent_request();
    }
    public function on_kernel_terminate(Terminate_Event $event): void
    {
        // attach children to parents
        foreach ($this->profiles as $request) {
            if (null === $parent_request = $this->parents[$request]) {
                continue;
            }
            if (!isset($this->profiles[$parent_request])) {
                continue;
            }
            $this->profiles[$parent_request]->add_child($this->profiles[$request]);
        }
        // save profiles
        foreach ($this->profiles as $request) {
            $this->profiler->save_profile($this->profiles[$request]);
        }
        $this->reset();
    }
    public function reset(): void
    {
        $this->profiles = new \Spl_Object_Storage();
        $this->parents = new \Spl_Object_Storage();
        $this->exception = null;
    }
    public static function get_subscribed_events(): array
    {
        return [Kernel_Events::RESPONSE => ['onKernelResponse', -100], Kernel_Events::EXCEPTION => ['onKernelException', 0], Kernel_Events::TERMINATE => ['onKernelTerminate', -1024]];
    }
}