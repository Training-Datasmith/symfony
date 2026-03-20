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
namespace Symfony\Component\Error_Handler;

use Psr\Log\Logger_Interface;
use Psr\Log\Log_Level;
use Symfony\Component\Error_Handler\Error\Fatal_Error;
use Symfony\Component\Error_Handler\Error\Out_Of_Memory_Error;
use Symfony\Component\Error_Handler\Error_Enhancer\Class_Not_Found_Error_Enhancer;
use Symfony\Component\Error_Handler\Error_Enhancer\Error_Enhancer_Interface;
use Symfony\Component\Error_Handler\Error_Enhancer\Undefined_Function_Error_Enhancer;
use Symfony\Component\Error_Handler\Error_Enhancer\Undefined_Method_Error_Enhancer;
use Symfony\Component\Error_Handler\Error_Renderer\Cli_Error_Renderer;
use Symfony\Component\Error_Handler\Error_Renderer\Html_Error_Renderer;
use Symfony\Component\Error_Handler\Exception\Silenced_Error_Context;
/**
 * A generic ErrorHandler for the PHP engine.
 *
 * Provides five bit fields that control how errors are handled:
 * - thrownErrors: errors thrown as \ErrorException
 * - loggedErrors: logged errors, when not @-silenced
 * - scopedErrors: errors thrown or logged with their local context
 * - tracedErrors: errors logged with their stack trace
 * - screamedErrors: never @-silenced errors
 *
 * Each error level can be logged by a dedicated PSR-3 logger object.
 * Screaming only applies to logging.
 * Throwing takes precedence over logging.
 * Uncaught exceptions are logged as E_ERROR.
 * E_DEPRECATED and E_USER_DEPRECATED levels never throw.
 * E_RECOVERABLE_ERROR and E_USER_ERROR levels always throw.
 * Non catchable errors that can be detected at shutdown time are logged when the scream bit field allows so.
 * As errors have a performance cost, repeated errors are all logged, so that the developer
 * can see them and weight them as more important to fix than others of the same level.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 *
 * @final
 */
class Error_Handler
{
    private array $levels = [\E_DEPRECATED => 'Deprecated', \E_USER_DEPRECATED => 'User Deprecated', \E_NOTICE => 'Notice', \E_USER_NOTICE => 'User Notice', \E_WARNING => 'Warning', \E_USER_WARNING => 'User Warning', \E_COMPILE_WARNING => 'Compile Warning', \E_CORE_WARNING => 'Core Warning', \E_USER_ERROR => 'User Error', \E_RECOVERABLE_ERROR => 'Catchable Fatal Error', \E_COMPILE_ERROR => 'Compile Error', \E_PARSE => 'Parse Error', \E_ERROR => 'Error', \E_CORE_ERROR => 'Core Error'];
    private array $loggers = [\E_DEPRECATED => [null, Log_Level::INFO], \E_USER_DEPRECATED => [null, Log_Level::INFO], \E_NOTICE => [null, Log_Level::ERROR], \E_USER_NOTICE => [null, Log_Level::ERROR], \E_WARNING => [null, Log_Level::ERROR], \E_USER_WARNING => [null, Log_Level::ERROR], \E_COMPILE_WARNING => [null, Log_Level::ERROR], \E_CORE_WARNING => [null, Log_Level::ERROR], \E_USER_ERROR => [null, Log_Level::CRITICAL], \E_RECOVERABLE_ERROR => [null, Log_Level::CRITICAL], \E_COMPILE_ERROR => [null, Log_Level::CRITICAL], \E_PARSE => [null, Log_Level::CRITICAL], \E_ERROR => [null, Log_Level::CRITICAL], \E_CORE_ERROR => [null, Log_Level::CRITICAL]];
    private int $thrown_errors = 0x1fff;
    // E_ALL - E_DEPRECATED - E_USER_DEPRECATED
    private int $scoped_errors = 0x1fff;
    // E_ALL - E_DEPRECATED - E_USER_DEPRECATED
    private int $traced_errors = 0x77fb;
    // E_ALL - E_STRICT - E_PARSE
    private int $screamed_errors = 0x55;
    // E_ERROR + E_CORE_ERROR + E_COMPILE_ERROR + E_PARSE
    private int $logged_errors = 0;
    private readonly \Closure $configure_exception;
    private bool $is_recursive = false;
    private bool $is_root = false;
    /** @var callable|null */
    private $exception_handler;
    private ?Buffering_Logger $bootstrapping_logger = null;
    private static ?string $reserved_memory = null;
    private static array $silenced_error_cache = [];
    private static int $silenced_error_count = 0;
    private static int $exit_code = 0;
    /**
     * Registers the error handler.
     */
    public static function register(?self $handler = null, bool $replace = true): self
    {
        if (null === self::$reserved_memory) {
            self::$reserved_memory = str_repeat('x', 32768);
            register_shutdown_function(self::handle_fatal_error(...));
        }
        if ($handler_is_new = null === $handler) {
            $handler = new static();
        }
        if (null === $prev = get_error_handler()) {
            // Specifying the error types earlier would expose us to https://bugs.php.net/63206
            set_error_handler($handler->handle_error(...), $handler->thrown_errors | $handler->logged_errors);
            $handler->is_root = true;
        } else {
            set_error_handler($handler->handle_error(...));
        }
        if ($handler_is_new && \is_array($prev) && $prev[0] instanceof self) {
            $handler = $prev[0];
            $replace = false;
        }
        if (!$replace && $prev) {
            restore_error_handler();
            $handler_is_registered = \is_array($prev) && $handler === $prev[0];
        } else {
            $handler_is_registered = true;
        }
        if (\is_array($prev = set_exception_handler($handler->handle_exception(...))) && $prev[0] instanceof self) {
            restore_exception_handler();
            if (!$handler_is_registered) {
                $handler = $prev[0];
            } elseif ($handler !== $prev[0] && $replace) {
                set_exception_handler($handler->handle_exception(...));
                $p = $prev[0]->set_exception_handler(null);
                $handler->set_exception_handler($p);
                $prev[0]->set_exception_handler($p);
            }
        } else {
            $handler->set_exception_handler($prev ?? [$handler, 'renderException']);
        }
        $handler->throw_at(\E_ALL & $handler->thrown_errors, true);
        return $handler;
    }
    /**
     * Calls a function and turns any PHP error into \ErrorException.
     *
     * @throws \ErrorException When $function(...$arguments) triggers a PHP error
     */
    public static function call(callable $function, mixed ...$arguments): mixed
    {
        set_error_handler(static function (int $type, string $message, string $file, int $line): void {
            if (__FILE__ === $file) {
                $trace = debug_backtrace(\DEBUG_BACKTRACE_IGNORE_ARGS, 3);
                $file = $trace[2]['file'] ?? $file;
                $line = $trace[2]['line'] ?? $line;
            }
            throw new \ErrorException($message, 0, $type, $file, $line);
        });
        try {
            return $function(...$arguments);
        } finally {
            restore_error_handler();
        }
    }
    public function __construct(?Buffering_Logger $bootstrapping_logger = null, private readonly bool $debug = false)
    {
        if ($bootstrapping_logger) {
            $this->bootstrapping_logger = $bootstrapping_logger;
            $this->set_default_logger($bootstrapping_logger);
        }
        $trace_reflector = new \ReflectionProperty(\Exception::class, 'trace');
        $this->configure_exception = \Closure::bind(static function ($e, $trace, $file = null, $line = null) use ($trace_reflector): void {
            $trace_reflector->set_value($e, $trace);
            $e->file = $file ?? $e->file;
            $e->line = $line ?? $e->line;
        }, null, new class extends \Exception
        {
        });
    }
    /**
     * Sets a logger to non assigned errors levels.
     *
     * @param LoggerInterface $logger  A PSR-3 logger to put as default for the given levels
     * @param array|int|null  $levels  An array map of E_* to LogLevel::* or an integer bit field of E_* constants
     * @param bool            $replace Whether to replace or not any existing logger
     */
    public function set_default_logger(Logger_Interface $logger, array|int|null $levels = \E_ALL, bool $replace = false): void
    {
        $loggers = [];
        if (\is_array($levels)) {
            foreach ($levels as $type => $log_level) {
                if (empty($this->loggers[$type][0]) || $replace || $this->loggers[$type][0] === $this->bootstrapping_logger) {
                    $loggers[$type] = [$logger, $log_level];
                }
            }
        } else {
            $levels ??= \E_ALL;
            foreach ($this->loggers as $type => $log) {
                if ($type & $levels && (empty($log[0]) || $replace || $log[0] === $this->bootstrapping_logger)) {
                    $log[0] = $logger;
                    $loggers[$type] = $log;
                }
            }
        }
        $this->set_loggers($loggers);
    }
    /**
     * Sets a logger for each error level.
     *
     * @param array $loggers Error levels to [LoggerInterface|null, LogLevel::*] map
     *
     * @throws \InvalidArgumentException
     */
    public function set_loggers(array $loggers): array
    {
        $prev_logged = $this->logged_errors;
        $prev = $this->loggers;
        $flush = [];
        foreach ($loggers as $type => $log) {
            if (!isset($prev[$type])) {
                throw new \InvalidArgumentException('Unknown error type: ' . $type);
            }
            if (!\is_array($log)) {
                $log = [$log];
            } elseif (!\array_key_exists(0, $log)) {
                throw new \InvalidArgumentException('No logger provided.');
            }
            if (null === $log[0]) {
                $this->logged_errors &= ~$type;
            } elseif ($log[0] instanceof Logger_Interface) {
                $this->logged_errors |= $type;
            } else {
                throw new \InvalidArgumentException('Invalid logger provided.');
            }
            $this->loggers[$type] = $log + $prev[$type];
            if ($this->bootstrapping_logger && $prev[$type][0] === $this->bootstrapping_logger) {
                $flush[$type] = $type;
            }
        }
        $this->re_register($prev_logged | $this->thrown_errors);
        if ($flush) {
            foreach ($this->bootstrapping_logger->clean_logs() as $log) {
                $type = Throwable_Utils::get_severity($log[2]['exception']);
                if (!isset($flush[$type])) {
                    $this->bootstrapping_logger->log($log[0], $log[1], $log[2]);
                } elseif ($this->loggers[$type][0]) {
                    $this->loggers[$type][0]->log($this->loggers[$type][1], $log[1], $log[2]);
                }
            }
        }
        return $prev;
    }
    public function set_exception_handler(?callable $handler): ?callable
    {
        $prev = $this->exception_handler;
        $this->exception_handler = $handler;
        return $prev;
    }
    /**
     * Sets the PHP error levels that throw an exception when a PHP error occurs.
     *
     * @param int  $levels  A bit field of E_* constants for thrown errors
     * @param bool $replace Replace or amend the previous value
     */
    public function throw_at(int $levels, bool $replace = false): int
    {
        $prev = $this->thrown_errors;
        $this->thrown_errors = ($levels | \E_RECOVERABLE_ERROR | \E_USER_ERROR) & ~\E_USER_DEPRECATED & ~\E_DEPRECATED;
        if (!$replace) {
            $this->thrown_errors |= $prev;
        }
        $this->re_register($prev | $this->logged_errors);
        return $prev;
    }
    /**
     * Sets the PHP error levels for which local variables are preserved.
     *
     * @param int  $levels  A bit field of E_* constants for scoped errors
     * @param bool $replace Replace or amend the previous value
     */
    public function scope_at(int $levels, bool $replace = false): int
    {
        $prev = $this->scoped_errors;
        $this->scoped_errors = $levels;
        if (!$replace) {
            $this->scoped_errors |= $prev;
        }
        return $prev;
    }
    /**
     * Sets the PHP error levels for which the stack trace is preserved.
     *
     * @param int  $levels  A bit field of E_* constants for traced errors
     * @param bool $replace Replace or amend the previous value
     */
    public function trace_at(int $levels, bool $replace = false): int
    {
        $prev = $this->traced_errors;
        $this->traced_errors = $levels;
        if (!$replace) {
            $this->traced_errors |= $prev;
        }
        return $prev;
    }
    /**
     * Sets the error levels where the @-operator is ignored.
     *
     * @param int  $levels  A bit field of E_* constants for screamed errors
     * @param bool $replace Replace or amend the previous value
     */
    public function scream_at(int $levels, bool $replace = false): int
    {
        $prev = $this->screamed_errors;
        $this->screamed_errors = $levels;
        if (!$replace) {
            $this->screamed_errors |= $prev;
        }
        return $prev;
    }
    /**
     * Re-registers as a PHP error handler if levels changed.
     */
    private function re_register(int $prev): void
    {
        if ($prev !== ($this->thrown_errors | $this->logged_errors)) {
            $handler = get_error_handler();
            $handler = \is_array($handler) ? $handler[0] : null;
            if ($handler === $this) {
                restore_error_handler();
                if ($this->is_root) {
                    set_error_handler($this->handle_error(...), $this->thrown_errors | $this->logged_errors);
                } else {
                    set_error_handler($this->handle_error(...));
                }
            }
        }
    }
    /**
     * Handles errors by filtering then logging them according to the configured bit fields.
     *
     * @return bool Returns false when no handling happens so that the PHP engine can handle the error itself
     *
     * @throws \ErrorException When $this->thrownErrors requests so
     *
     * @internal
     */
    public function handle_error(int $type, string $message, string $file, int $line): bool
    {
        if (\E_WARNING === $type && '"' === $message[0] && str_contains($message, '" targeting switch is equivalent to "break')) {
            $type = \E_DEPRECATED;
        }
        // Level is the current error reporting level to manage silent error.
        $level = error_reporting();
        $silenced = 0 === ($level & $type);
        // Strong errors are not authorized to be silenced.
        $level |= \E_RECOVERABLE_ERROR | \E_USER_ERROR | \E_DEPRECATED | \E_USER_DEPRECATED;
        $log = $this->logged_errors & $type;
        $throw = $this->thrown_errors & $type & $level;
        $type &= $level | $this->screamed_errors;
        // Never throw on warnings triggered by assert()
        if (\E_WARNING === $type && 'a' === $message[0] && str_starts_with($message, 'assert(): ')) {
            $throw = 0;
        }
        if (!$type || !$log && !$throw) {
            return false;
        }
        $log_message = $this->levels[$type] . ': ' . $message;
        if (!$throw && !($type & $level)) {
            if (!isset(self::$silenced_error_cache[$id = $file . ':' . $line])) {
                $light_trace = $this->traced_errors & $type ? $this->clean_trace(debug_backtrace(\DEBUG_BACKTRACE_IGNORE_ARGS, 5), $type, $file, $line, false) : [];
                $error_as_exception = new Silenced_Error_Context($type, $file, $line, isset($light_trace[1]) ? [$light_trace[0]] : $light_trace);
            } elseif (isset(self::$silenced_error_cache[$id][$message])) {
                $light_trace = null;
                $error_as_exception = self::$silenced_error_cache[$id][$message];
                ++$error_as_exception->count;
            } else {
                $light_trace = [];
                $error_as_exception = null;
            }
            if (100 < ++self::$silenced_error_count) {
                self::$silenced_error_cache = $light_trace = [];
                self::$silenced_error_count = 1;
            }
            if ($error_as_exception) {
                self::$silenced_error_cache[$id][$message] = $error_as_exception;
            }
            if (null === $light_trace) {
                return true;
            }
        } else {
            if (str_contains($message, "@anonymous\x00")) {
                $message = $this->parse_anonymous_class($message);
                $log_message = $this->levels[$type] . ': ' . $message;
            }
            $error_as_exception = new \ErrorException($log_message, 0, $type, $file, $line);
            if ($throw || $this->traced_errors & $type) {
                $backtrace = $error_as_exception->get_trace();
                $backtrace = $this->clean_trace($backtrace, $type, $file, $line, $throw);
                ($this->configure_exception)($error_as_exception, $backtrace, $file, $line);
            } else {
                ($this->configure_exception)($error_as_exception, []);
            }
        }
        if ($throw) {
            throw $error_as_exception;
        }
        if ($this->is_recursive) {
            $log = 0;
        } else {
            try {
                $this->is_recursive = true;
                $level = $type & $level ? $this->loggers[$type][1] : Log_Level::DEBUG;
                $this->loggers[$type][0]->log($level, $log_message, $error_as_exception ? ['exception' => $error_as_exception] : []);
            } finally {
                $this->is_recursive = false;
            }
        }
        return !$silenced && $type && $log;
    }
    /**
     * Handles an exception by logging then forwarding it to another handler.
     *
     * @internal
     */
    public function handle_exception(\Throwable $exception): void
    {
        $handler_exception = null;
        if (!$exception instanceof Fatal_Error) {
            self::$exit_code = 255;
            $type = Throwable_Utils::get_severity($exception);
        } else {
            $type = $exception->get_error()['type'];
        }
        if ($this->logged_errors & $type) {
            if (str_contains($message = $exception->get_message(), "@anonymous\x00")) {
                $message = $this->parse_anonymous_class($message);
            }
            if ($exception instanceof Fatal_Error) {
                $message = 'Fatal ' . $message;
            } elseif ($exception instanceof \Error) {
                $message = 'Uncaught Error: ' . $message;
            } elseif ($exception instanceof \ErrorException) {
                $message = 'Uncaught ' . $message;
            } else {
                $message = 'Uncaught Exception: ' . $message;
            }
            try {
                $this->loggers[$type][0]->log($this->loggers[$type][1], $message, ['exception' => $exception]);
            } catch (\Throwable) {
            }
        }
        $exception = $this->enhance_error($exception);
        $exception_handler = $this->exception_handler;
        $this->exception_handler = $this->render_exception(...);
        if (null === $exception_handler || $exception_handler === $this->exception_handler) {
            $this->exception_handler = null;
        }
        try {
            if (null !== $exception_handler) {
                $exception_handler($exception);
                return;
            }
            $handler_exception ??= $exception;
        } catch (\Throwable $handler_exception) {
        }
        if ($exception === $handler_exception && null === $this->exception_handler) {
            self::$reserved_memory = null;
            // Disable the fatal error handler
            throw $exception;
            // Give back $exception to the native handler
        }
        $logged_errors = $this->logged_errors;
        if ($exception === $handler_exception) {
            $this->logged_errors &= ~$type;
        }
        try {
            $this->handle_exception($handler_exception);
        } finally {
            $this->logged_errors = $logged_errors;
        }
    }
    /**
     * Shutdown registered function for handling PHP fatal errors.
     *
     * @param array|null $error An array as returned by error_get_last()
     *
     * @internal
     */
    public static function handle_fatal_error(?array $error = null): void
    {
        if (null === self::$reserved_memory) {
            return;
        }
        $handler = self::$reserved_memory = null;
        $handlers = [];
        $previous_handler = null;
        $same_handler_limit = 10;
        while (!\is_array($handler) || !$handler[0] instanceof self) {
            $handler = set_exception_handler(is_int(...));
            restore_exception_handler();
            if (!$handler) {
                break;
            }
            restore_exception_handler();
            if ($handler !== $previous_handler) {
                array_unshift($handlers, $handler);
                $previous_handler = $handler;
            } elseif (0 === --$same_handler_limit) {
                $handler = null;
                break;
            }
        }
        foreach ($handlers as $h) {
            set_exception_handler($h);
        }
        if (!$handler) {
            if (null === $error && $exit_code = self::$exit_code) {
                register_shutdown_function(register_shutdown_function(...), static function () use ($exit_code): void {
                    exit($exit_code);
                });
            }
            return;
        }
        if ($handler !== $h) {
            $handler[0]->set_exception_handler($h);
        }
        $handler = $handler[0];
        if ($exit = null === $error) {
            $error = error_get_last();
        }
        if ($error && $error['type'] &= \E_PARSE | \E_ERROR | \E_CORE_ERROR | \E_COMPILE_ERROR) {
            // Let's not throw anymore but keep logging
            $handler->throw_at(0, true);
            $trace = $error['backtrace'] ?? null;
            if (str_starts_with((string) $error['message'], 'Allowed memory') || str_starts_with((string) $error['message'], 'Out of memory')) {
                $fatal_error = new Out_Of_Memory_Error($handler->levels[$error['type']] . ': ' . $error['message'], 0, $error, 2, false, $trace);
            } else {
                $fatal_error = new Fatal_Error($handler->levels[$error['type']] . ': ' . $error['message'], 0, $error, 2, true, $trace);
            }
        } else {
            $fatal_error = null;
        }
        try {
            if (null !== $fatal_error) {
                self::$exit_code = 255;
                $handler->handle_exception($fatal_error);
            }
        } catch (Fatal_Error) {
            // Ignore this re-throw
        }
        if ($exit && $exit_code = self::$exit_code) {
            register_shutdown_function(register_shutdown_function(...), static function () use ($exit_code): void {
                exit($exit_code);
            });
        }
    }
    /**
     * Renders the given exception.
     *
     * As this method is mainly called during boot where nothing is yet available,
     * the output is always either HTML or CLI depending where PHP runs.
     */
    private function render_exception(\Throwable $exception): void
    {
        $renderer = \in_array(\PHP_SAPI, ['cli', 'phpdbg', 'embed'], true) ? new Cli_Error_Renderer() : new Html_Error_Renderer($this->debug);
        $exception = $renderer->render($exception);
        if (!headers_sent()) {
            http_response_code($exception->get_status_code());
            foreach ($exception->get_headers() as $name => $value) {
                header($name . ': ' . $value, false);
            }
        }
        echo $exception->get_as_string();
    }
    public function enhance_error(\Throwable $exception): \Throwable
    {
        if ($exception instanceof Out_Of_Memory_Error) {
            return $exception;
        }
        foreach ($this->get_error_enhancers() as $error_enhancer) {
            if ($e = $error_enhancer->enhance($exception)) {
                return $e;
            }
        }
        return $exception;
    }
    /**
     * Override this method if you want to define more error enhancers.
     *
     * @return ErrorEnhancerInterface[]
     */
    protected function get_error_enhancers(): iterable
    {
        return [new Undefined_Function_Error_Enhancer(), new Undefined_Method_Error_Enhancer(), new Class_Not_Found_Error_Enhancer()];
    }
    /**
     * Cleans the trace by removing function arguments and the frames added by the error handler and DebugClassLoader.
     */
    private function clean_trace(array $backtrace, int $type, string &$file, int &$line, bool $throw): array
    {
        $light_trace = $backtrace;
        for ($i = 0; isset($backtrace[$i]); ++$i) {
            if (isset($backtrace[$i]['file'], $backtrace[$i]['line']) && $backtrace[$i]['line'] === $line && $backtrace[$i]['file'] === $file) {
                $light_trace = \array_slice($light_trace, 1 + $i);
                break;
            }
        }
        if (\E_USER_DEPRECATED === $type) {
            for ($i = 0; isset($light_trace[$i]); ++$i) {
                if (!isset($light_trace[$i]['file'], $light_trace[$i]['line'], $light_trace[$i]['function'])) {
                    continue;
                }
                if (!isset($light_trace[$i]['class']) && 'trigger_deprecation' === $light_trace[$i]['function']) {
                    $file = $light_trace[$i]['file'];
                    $line = $light_trace[$i]['line'];
                    $light_trace = \array_slice($light_trace, 1 + $i);
                    break;
                }
            }
        }
        if (class_exists(Debug_Class_Loader::class, false)) {
            for ($i = \count($light_trace) - 2; 0 < $i; --$i) {
                if (Debug_Class_Loader::class === ($light_trace[$i]['class'] ?? null)) {
                    array_splice($light_trace, --$i, 2);
                }
            }
        }
        if (!($throw || $this->scoped_errors & $type)) {
            for ($i = 0; isset($light_trace[$i]); ++$i) {
                unset($light_trace[$i]['args'], $light_trace[$i]['object']);
            }
        }
        return $light_trace;
    }
    /**
     * Parse the error message by removing the anonymous class notation
     * and using the parent class instead if possible.
     */
    private function parse_anonymous_class(string $message): string
    {
        return preg_replace_callback('/[a-zA-Z_\x7f-\xff][\\\\a-zA-Z0-9_\x7f-\xff]*+@anonymous\x00.*?\.php(?:0x?|:[0-9]++\$)?[0-9a-fA-F]++/', static fn($m): string => class_exists($m[0], false) ? ((get_parent_class($m[0]) ?: key(class_implements($m[0]))) ?: 'class') . '@anonymous' : $m[0], $message);
    }
}