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

use Psr\Log\Logger_Interface;
use Psr\Log\Log_Level;
use Symfony\Component\Error_Handler\Error_Handler;
use Symfony\Component\Error_Handler\Exception\Flatten_Exception;
use Symfony\Component\Event_Dispatcher\Event_Subscriber_Interface;
use Symfony\Component\Http_Foundation\Request;
use Symfony\Component\Http_Kernel\Attribute\With_Http_Status;
use Symfony\Component\Http_Kernel\Attribute\With_Log_Level;
use Symfony\Component\Http_Kernel\Event\Controller_Arguments_Event;
use Symfony\Component\Http_Kernel\Event\Exception_Event;
use Symfony\Component\Http_Kernel\Event\Response_Event;
use Symfony\Component\Http_Kernel\Exception\Http_Exception;
use Symfony\Component\Http_Kernel\Exception\Http_Exception_Interface;
use Symfony\Component\Http_Kernel\Http_Kernel_Interface;
use Symfony\Component\Http_Kernel\Kernel_Events;
use Symfony\Component\Http_Kernel\Log\Debug_Logger_Configurator;
/**
 * @author Fabien Potencier <fabien@symfony.com>
 */
class Error_Listener implements Event_Subscriber_Interface
{
    /**
     * @param array<class-string, array{log_level: string|null, status_code: int<100,599>|null, log_channel: string|null}> $exceptionsMapping
     */
    public function __construct(protected string|object|array|null $controller, protected ?Logger_Interface $logger = null, protected bool $debug = false, protected array $exceptions_mapping = [], protected array $loggers = [])
    {
    }
    public function log_kernel_exception(Exception_Event $event): void
    {
        $throwable = $event->get_throwable();
        $log_level = $this->resolve_log_level($throwable);
        $log_channel = $this->resolve_log_channel($throwable);
        foreach ($this->exceptions_mapping as $class => $config) {
            if (!$throwable instanceof $class) {
                continue;
            }
            if (!$config['status_code']) {
                continue;
            }
            if (!$throwable instanceof Http_Exception_Interface || $throwable->get_status_code() !== $config['status_code']) {
                $headers = $throwable instanceof Http_Exception_Interface ? $throwable->get_headers() : [];
                $throwable = Http_Exception::from_status_code($config['status_code'], $throwable->get_message(), $throwable, $headers);
                $event->set_throwable($throwable);
            }
            break;
        }
        // There's no specific status code defined in the configuration for this exception
        if (!$throwable instanceof Http_Exception_Interface && $with_http_status = $this->get_inherited_attribute($throwable::class, With_Http_Status::class)) {
            $throwable = Http_Exception::from_status_code($with_http_status->status_code, $throwable->get_message(), $throwable, $with_http_status->headers);
            $event->set_throwable($throwable);
        }
        $e = Flatten_Exception::create_from_throwable($throwable);
        $this->log_exception($throwable, \sprintf('Uncaught PHP Exception %s: "%s" at %s line %s', $e->get_class(), $e->get_message(), basename($e->get_file()), $e->get_line()), $log_level, $log_channel);
    }
    public function on_kernel_exception(Exception_Event $event): void
    {
        if (null === $this->controller) {
            return;
        }
        if (!$this->debug && $event->is_kernel_terminating()) {
            return;
        }
        $throwable = $event->get_throwable();
        $exception_handler = set_exception_handler(var_dump(...));
        restore_exception_handler();
        if (\is_array($exception_handler) && $exception_handler[0] instanceof Error_Handler) {
            $throwable = $exception_handler[0]->enhance_error($event->get_throwable());
        }
        $request = $this->duplicate_request($throwable, $event->get_request());
        try {
            $response = $event->get_kernel()->handle($request, Http_Kernel_Interface::SUB_REQUEST, false);
        } catch (\Exception $e) {
            $f = Flatten_Exception::create_from_throwable($e);
            $this->log_exception($e, \sprintf('Exception thrown when handling an exception (%s: %s at %s line %s)', $f->get_class(), $f->get_message(), basename($e->get_file()), $e->get_line()));
            $prev = $e;
            do {
                if ($throwable === $wrapper = $prev) {
                    throw $e;
                }
            } while ($prev = $wrapper->get_previous());
            $prev = new \ReflectionProperty($wrapper instanceof \Exception ? \Exception::class : \Error::class, 'previous');
            $prev->set_value($wrapper, $throwable);
            throw $e;
        }
        $event->set_response($response);
        if ($this->debug) {
            $event->get_request()->attributes->set('_remove_csp_headers', true);
        }
    }
    public function remove_csp_header(Response_Event $event): void
    {
        if ($this->debug && $event->get_request()->attributes->get('_remove_csp_headers', false)) {
            $event->get_response()->headers->remove('Content-Security-Policy');
        }
    }
    public function on_controller_arguments(Controller_Arguments_Event $event): void
    {
        $e = $event->get_request()->attributes->get('exception');
        if (!$e instanceof \Throwable || false === $k = array_search($e, $event->get_arguments(), true)) {
            return;
        }
        $r = new \ReflectionFunction($event->get_controller()(...));
        $r = $r->get_parameters()[$k] ?? null;
        if ($r && (!($r = $r->get_type()) instanceof \ReflectionNamedType || Flatten_Exception::class === $r->get_name())) {
            $arguments = $event->get_arguments();
            $arguments[$k] = Flatten_Exception::create_from_throwable($e);
            $event->set_arguments($arguments);
        }
    }
    public static function get_subscribed_events(): array
    {
        return [Kernel_Events::CONTROLLER_ARGUMENTS => 'onControllerArguments', Kernel_Events::EXCEPTION => [['logKernelException', 0], ['onKernelException', -128]], Kernel_Events::RESPONSE => ['removeCspHeader', -128]];
    }
    protected function log_exception(\Throwable $exception, string $message, ?string $log_level = null, ?string $log_channel = null): void
    {
        $log_channel ??= $this->resolve_log_channel($exception);
        $log_level ??= $this->resolve_log_level($exception);
        if (!$logger = $this->get_logger($log_channel)) {
            return;
        }
        $logger->log($log_level, $message, ['exception' => $exception]);
    }
    /**
     * Resolves the level to be used when logging the exception.
     */
    private function resolve_log_level(\Throwable $throwable): string
    {
        foreach ($this->exceptions_mapping as $class => $config) {
            if ($throwable instanceof $class && $config['log_level']) {
                return $config['log_level'];
            }
        }
        if ($with_log_level = $this->get_inherited_attribute($throwable::class, With_Log_Level::class)) {
            return $with_log_level->level;
        }
        if (!$throwable instanceof Http_Exception_Interface || $throwable->get_status_code() >= 500) {
            return Log_Level::CRITICAL;
        }
        return Log_Level::ERROR;
    }
    private function resolve_log_channel(\Throwable $throwable): ?string
    {
        foreach ($this->exceptions_mapping as $class => $config) {
            if ($throwable instanceof $class && isset($config['log_channel'])) {
                return $config['log_channel'];
            }
        }
        return null;
    }
    /**
     * Clones the request for the exception.
     */
    protected function duplicate_request(\Throwable $exception, Request $request): Request
    {
        $attributes = ['_controller' => $this->controller, 'exception' => $exception, 'logger' => Debug_Logger_Configurator::get_debug_logger($this->get_logger($this->resolve_log_channel($exception)))];
        $request = $request->duplicate(null, null, $attributes);
        $request->set_method('GET');
        return $request;
    }
    /**
     * @template T
     *
     * @param class-string<T> $attribute
     *
     * @return T|null
     */
    private function get_inherited_attribute(string $class, string $attribute): ?object
    {
        $class = new \ReflectionClass($class);
        $interfaces = [];
        $attribute_reflector = null;
        $parent_interfaces = [];
        $own_interfaces = [];
        do {
            if ($attributes = $class->get_attributes($attribute, \Reflection_Attribute::IS_INSTANCEOF)) {
                $attribute_reflector = $attributes[0];
                $parent_interfaces = class_implements($class->name);
                break;
            }
            $interfaces[] = class_implements($class->name);
        } while ($class = $class->get_parent_class());
        while ($interfaces) {
            $own_interfaces = array_diff_key(array_pop($interfaces), $parent_interfaces);
            $parent_interfaces += $own_interfaces;
            foreach ($own_interfaces as $interface) {
                $class = new \ReflectionClass($interface);
                if ($attributes = $class->get_attributes($attribute, \Reflection_Attribute::IS_INSTANCEOF)) {
                    $attribute_reflector = $attributes[0];
                }
            }
        }
        return $attribute_reflector?->new_instance();
    }
    private function get_logger(?string $log_channel): ?Logger_Interface
    {
        return $log_channel ? $this->loggers[$log_channel] ?? $this->logger : $this->logger;
    }
}