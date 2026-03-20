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
namespace Symfony\Component\Error_Handler\Exception;

use Symfony\Component\Http_Foundation\Exception\Request_Exception_Interface;
use Symfony\Component\Http_Foundation\Response;
use Symfony\Component\Http_Kernel\Exception\Http_Exception_Interface;
use Symfony\Component\Var_Dumper\Caster\Caster;
use Symfony\Component\Var_Dumper\Cloner\Data;
use Symfony\Component\Var_Dumper\Cloner\Stub;
use Symfony\Component\Var_Dumper\Cloner\Var_Cloner;
/**
 * FlattenException wraps a PHP Error or Exception to be able to serialize it.
 *
 * Basically, this class removes all objects from the trace.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class Flatten_Exception
{
    private string $message;
    private string|int $code;
    private ?self $previous = null;
    private array $trace;
    private string $trace_as_string;
    private string $class;
    private int $status_code;
    private string $status_text;
    private array $headers;
    private string $file;
    private int $line;
    private ?string $as_string = null;
    private Data $data_representation;
    public static function create(\Exception $exception, ?int $status_code = null, array $headers = []): static
    {
        return static::create_from_throwable($exception, $status_code, $headers);
    }
    public static function create_from_throwable(\Throwable $exception, ?int $status_code = null, array $headers = []): static
    {
        $e = new static();
        $e->set_message($exception->get_message());
        $e->set_code($exception->get_code());
        if ($exception instanceof Http_Exception_Interface) {
            $status_code = $exception->get_status_code();
            $headers = array_merge($headers, $exception->get_headers());
        } elseif ($exception instanceof Request_Exception_Interface) {
            $status_code = 400;
        }
        $status_code ??= 500;
        if (class_exists(Response::class) && isset(Response::$status_texts[$status_code])) {
            $status_text = Response::$status_texts[$status_code];
        } else {
            $status_text = 'Whoops, looks like something went wrong.';
        }
        $e->set_status_text($status_text);
        $e->set_status_code($status_code);
        $e->set_headers($headers);
        $e->set_trace_from_throwable($exception);
        $e->set_class(get_debug_type($exception));
        $e->set_file($exception->get_file());
        $e->set_line($exception->get_line());
        $previous = $exception->get_previous();
        if ($previous instanceof \Throwable) {
            $e->set_previous(static::create_from_throwable($previous));
        }
        return $e;
    }
    public static function create_with_data_representation(\Throwable $throwable, ?int $status_code = null, array $headers = [], ?Var_Cloner $cloner = null): static
    {
        $e = static::create_from_throwable($throwable, $status_code, $headers);
        static $default_cloner;
        if (!$cloner ??= $default_cloner) {
            $cloner = $default_cloner = new Var_Cloner();
            $cloner->add_casters([\Throwable::class => static function (\Throwable $e, array $a, Stub $s, bool $is_nested): array {
                if (!$is_nested) {
                    unset($a[Caster::PREFIX_PROTECTED . 'message']);
                    unset($a[Caster::PREFIX_PROTECTED . 'code']);
                    unset($a[Caster::PREFIX_PROTECTED . 'file']);
                    unset($a[Caster::PREFIX_PROTECTED . 'line']);
                    unset($a["\x00Error\x00trace"], $a["\x00Exception\x00trace"]);
                    unset($a["\x00Error\x00previous"], $a["\x00Exception\x00previous"]);
                }
                return $a;
            }]);
        }
        return $e->set_data_representation($cloner->clone_var($throwable));
    }
    public function to_array(): array
    {
        $exceptions = [];
        foreach (array_merge([$this], $this->get_all_previous()) as $exception) {
            $exceptions[] = ['message' => $exception->get_message(), 'class' => $exception->get_class(), 'trace' => $exception->get_trace(), 'data' => $exception->get_data_representation()];
        }
        return $exceptions;
    }
    public function get_status_code(): int
    {
        return $this->status_code;
    }
    /**
     * @return $this
     */
    public function set_status_code(int $code): static
    {
        $this->status_code = $code;
        return $this;
    }
    public function get_headers(): array
    {
        return $this->headers;
    }
    /**
     * @return $this
     */
    public function set_headers(array $headers): static
    {
        $this->headers = $headers;
        return $this;
    }
    public function get_class(): string
    {
        return $this->class;
    }
    /**
     * @return $this
     */
    public function set_class(string $class): static
    {
        $this->class = str_contains($class, "@anonymous\x00") ? ((get_parent_class($class) ?: key(class_implements($class))) ?: 'class') . '@anonymous' : $class;
        return $this;
    }
    public function get_file(): string
    {
        return $this->file;
    }
    /**
     * @return $this
     */
    public function set_file(string $file): static
    {
        $this->file = $file;
        return $this;
    }
    public function get_line(): int
    {
        return $this->line;
    }
    /**
     * @return $this
     */
    public function set_line(int $line): static
    {
        $this->line = $line;
        return $this;
    }
    public function get_status_text(): string
    {
        return $this->status_text;
    }
    /**
     * @return $this
     */
    public function set_status_text(string $status_text): static
    {
        $this->status_text = $status_text;
        return $this;
    }
    public function get_message(): string
    {
        return $this->message;
    }
    /**
     * @return $this
     */
    public function set_message(string $message): static
    {
        if (str_contains($message, "@anonymous\x00")) {
            $message = preg_replace_callback('/[a-zA-Z_\x7f-\xff][\\\\a-zA-Z0-9_\x7f-\xff]*+@anonymous\x00.*?\.php(?:0x?|:[0-9]++\$)?[0-9a-fA-F]++/', static fn($m): string => class_exists($m[0], false) ? ((get_parent_class($m[0]) ?: key(class_implements($m[0]))) ?: 'class') . '@anonymous' : $m[0], $message);
        }
        $this->message = $message;
        return $this;
    }
    /**
     * @return int|string int most of the time (might be a string with PDOException)
     */
    public function get_code(): int|string
    {
        return $this->code;
    }
    /**
     * @return $this
     */
    public function set_code(int|string $code): static
    {
        $this->code = $code;
        return $this;
    }
    public function get_previous(): ?self
    {
        return $this->previous;
    }
    /**
     * @return $this
     */
    public function set_previous(?self $previous): static
    {
        $this->previous = $previous;
        return $this;
    }
    /**
     * @return self[]
     */
    public function get_all_previous(): array
    {
        $exceptions = [];
        $e = $this;
        while ($e = $e->get_previous()) {
            $exceptions[] = $e;
        }
        return $exceptions;
    }
    public function get_trace(): array
    {
        return $this->trace;
    }
    /**
     * @return $this
     */
    public function set_trace_from_throwable(\Throwable $throwable): static
    {
        $this->trace_as_string = $throwable->get_trace_as_string();
        return $this->set_trace($throwable->get_trace(), $throwable->get_file(), $throwable->get_line());
    }
    /**
     * @return $this
     */
    public function set_trace(array $trace, ?string $file, ?int $line): static
    {
        $this->trace = [];
        $this->trace[] = ['namespace' => '', 'short_class' => '', 'class' => '', 'type' => '', 'function' => '', 'file' => $file, 'line' => $line, 'args' => []];
        foreach ($trace as $entry) {
            $class = '';
            $namespace = '';
            if (isset($entry['class'])) {
                $parts = explode('\\', $entry['class']);
                $class = array_pop($parts);
                $namespace = implode('\\', $parts);
            }
            $this->trace[] = ['namespace' => $namespace, 'short_class' => $class, 'class' => $entry['class'] ?? '', 'type' => $entry['type'] ?? '', 'function' => $entry['function'] ?? null, 'file' => $entry['file'] ?? null, 'line' => $entry['line'] ?? null, 'args' => isset($entry['args']) ? $this->flatten_args($entry['args']) : []];
        }
        return $this;
    }
    public function get_data_representation(): ?Data
    {
        return $this->data_representation ?? null;
    }
    /**
     * @return $this
     */
    public function set_data_representation(Data $data): static
    {
        $this->data_representation = $data;
        return $this;
    }
    private function flatten_args(array $args, int $level = 0, int &$count = 0): array
    {
        $result = [];
        foreach ($args as $key => $value) {
            if (++$count > 10000.0) {
                return ['array', '*SKIPPED over 10000 entries*'];
            }
            if ($value instanceof \__PHP_Incomplete_Class) {
                $result[$key] = ['incomplete-object', $this->get_class_name_from_incomplete($value)];
            } elseif (\is_object($value)) {
                $result[$key] = ['object', get_debug_type($value)];
            } elseif (\is_array($value)) {
                if ($level > 10) {
                    $result[$key] = ['array', '*DEEP NESTED ARRAY*'];
                } else {
                    $result[$key] = ['array', $this->flatten_args($value, $level + 1, $count)];
                }
            } elseif (null === $value) {
                $result[$key] = ['null', null];
            } elseif (\is_bool($value)) {
                $result[$key] = ['boolean', $value];
            } elseif (\is_int($value)) {
                $result[$key] = ['integer', $value];
            } elseif (\is_float($value)) {
                $result[$key] = ['float', $value];
            } elseif (\is_resource($value)) {
                $result[$key] = ['resource', get_resource_type($value)];
            } else {
                $result[$key] = ['string', (string) $value];
            }
        }
        return $result;
    }
    private function get_class_name_from_incomplete(\__PHP_Incomplete_Class $value): string
    {
        $array = new \ArrayObject($value);
        return $array['__PHP_Incomplete_Class_Name'];
    }
    public function get_trace_as_string(): string
    {
        return $this->trace_as_string;
    }
    /**
     * @return $this
     */
    public function set_as_string(?string $as_string): static
    {
        $this->as_string = $as_string;
        return $this;
    }
    public function get_as_string(): string
    {
        if (null !== $this->as_string) {
            return $this->as_string;
        }
        $message = '';
        $next = false;
        foreach (array_reverse(array_merge([$this], $this->get_all_previous())) as $exception) {
            if ($next) {
                $message .= 'Next ';
            } else {
                $next = true;
            }
            $message .= $exception->get_class();
            if ('' != $exception->get_message()) {
                $message .= ': ' . $exception->get_message();
            }
            $message .= ' in ' . $exception->get_file() . ':' . $exception->get_line() . "\nStack trace:\n" . $exception->get_trace_as_string() . "\n\n";
        }
        return rtrim($message);
    }
}