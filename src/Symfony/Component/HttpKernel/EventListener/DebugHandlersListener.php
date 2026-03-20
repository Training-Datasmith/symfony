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

use Symfony\Component\Console\Console_Events;
use Symfony\Component\Console\Event\Console_Event;
use Symfony\Component\Console\Output\Console_Output_Interface;
use Symfony\Component\Error_Handler\Error_Handler;
use Symfony\Component\Event_Dispatcher\Event_Subscriber_Interface;
use Symfony\Component\Http_Kernel\Event\Kernel_Event;
use Symfony\Component\Http_Kernel\Kernel_Events;
/**
 * Sets an exception handler.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 *
 * @final
 *
 * @internal
 */
class Debug_Handlers_Listener implements Event_Subscriber_Interface
{
    private readonly string|object|null $early_handler;
    private ?\Closure $exception_handler;
    private readonly bool $web_mode;
    private bool $first_call = true;
    private bool $has_terminated_with_exception = false;
    /**
     * @param callable|null $exceptionHandler A handler that must support \Throwable instances that will be called on Exception
     */
    public function __construct(?callable $exception_handler = null, ?bool $web_mode = null)
    {
        $handler = set_exception_handler(var_dump(...));
        $this->early_handler = \is_array($handler) ? $handler[0] : null;
        restore_exception_handler();
        $this->exception_handler = null === $exception_handler ? null : $exception_handler(...);
        $this->web_mode = $web_mode ?? !\in_array(\PHP_SAPI, ['cli', 'phpdbg', 'embed'], true);
    }
    /**
     * Configures the error handler.
     */
    public function configure(?object $event = null): void
    {
        if ($event instanceof Console_Event && $this->web_mode) {
            return;
        }
        if (!$event instanceof Kernel_Event ? !$this->first_call : !$event->is_main_request()) {
            return;
        }
        $this->first_call = $this->has_terminated_with_exception = false;
        $has_run = null;
        if (!$this->exception_handler) {
            if ($event instanceof Kernel_Event) {
                if (method_exists($kernel = $event->get_kernel(), 'terminateWithException')) {
                    $request = $event->get_request();
                    $has_run =& $this->has_terminated_with_exception;
                    $this->exception_handler = static function (\Throwable $e) use ($kernel, $request, &$has_run): void {
                        if ($has_run) {
                            throw $e;
                        }
                        $has_run = true;
                        $kernel->terminate_with_exception($e, $request);
                    };
                }
            } elseif ($event instanceof Console_Event && $app = $event->get_command()->get_application()) {
                $output = $event->get_output();
                if ($output instanceof Console_Output_Interface) {
                    $output = $output->get_error_output();
                }
                $this->exception_handler = static function (\Throwable $e) use ($app, $output): void {
                    $app->render_throwable($e, $output);
                };
            }
        }
        if ($this->exception_handler) {
            $handler = set_exception_handler(var_dump(...));
            $handler = \is_array($handler) ? $handler[0] : null;
            restore_exception_handler();
            if (!$handler instanceof Error_Handler) {
                $handler = $this->early_handler;
            }
            if ($handler instanceof Error_Handler) {
                $handler->set_exception_handler($this->exception_handler);
                if (null !== $has_run) {
                    $throw_at = $handler->throw_at(0) | \E_ERROR | \E_CORE_ERROR | \E_COMPILE_ERROR | \E_USER_ERROR | \E_RECOVERABLE_ERROR | \E_PARSE;
                    $loggers = [];
                    foreach ($handler->set_loggers([]) as $type => $log) {
                        if ($type & $throw_at) {
                            $loggers[$type] = [null, $log[1]];
                        }
                    }
                    // Assume $kernel->terminateWithException() will log uncaught exceptions appropriately
                    $handler->set_loggers($loggers);
                }
            }
            $this->exception_handler = null;
        }
    }
    public static function get_subscribed_events(): array
    {
        $events = [Kernel_Events::REQUEST => ['configure', 2048]];
        if (\defined('Symfony\Component\Console\ConsoleEvents::COMMAND')) {
            $events[Console_Events::COMMAND] = ['configure', 2048];
        }
        return $events;
    }
}