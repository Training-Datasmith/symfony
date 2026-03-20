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
namespace Symfony\Bridge\Php_Unit\Deprecation_Error_Handler;

use Doctrine\Deprecations\Deprecation as DoctrineDeprecation;
use Php_Unit\Framework\Test_Case;
use Php_Unit\Framework\Test_Suite;
use Php_Unit\Metadata\Api\Groups;
use Php_Unit\Util\Test;
use Symfony\Bridge\Php_Unit\Legacy\Symfony_Tests_Listener_For;
use Symfony\Component\Error_Handler\Debug_Class_Loader;
class_exists(Groups::class);
/**
 * @internal
 */
class Deprecation
{
    public const PATH_TYPE_VENDOR = 'path_type_vendor';
    public const PATH_TYPE_SELF = 'path_type_internal';
    public const PATH_TYPE_UNDETERMINED = 'path_type_undetermined';
    public const TYPE_SELF = 'type_self';
    public const TYPE_DIRECT = 'type_direct';
    public const TYPE_INDIRECT = 'type_indirect';
    public const TYPE_UNDETERMINED = 'type_undetermined';
    private array $trace;
    private $origin_class;
    private $origin_method;
    private string $triggering_file;
    private $triggering_class;
    /** @var string[] Absolute paths to vendor directories */
    private static ?array $vendors = null;
    /**
     * @var string[] Absolute paths to source or tests of the project, cache
     *               directories excluded because it is based on autoloading
     *               rules and cache systems typically do not use those
     */
    private static $internal_paths = [];
    private $original_files_stack;
    public function __construct(private string $message, array $trace, string $file, private readonly bool $language_deprecation = false)
    {
        if (Debug_Class_Loader::class === ($trace[2]['class'] ?? '')) {
            $this->triggering_class = $trace[2]['args'][0];
        }
        switch ($trace[2]['function'] ?? '') {
            case 'trigger_deprecation':
                $file = $trace[2]['file'];
                array_splice($trace, 1, 1);
                break;
            case 'delegateTriggerToBackend':
                if (Doctrine_Deprecation::class === ($trace[2]['class'] ?? '')) {
                    $file = $trace[3]['file'];
                    array_splice($trace, 1, 2);
                }
                break;
        }
        $this->trace = $trace;
        $i = \count($trace);
        while (1 < $i && $this->line_should_be_skipped($trace[--$i])) {
            // No-op
        }
        $line = $trace[$i];
        $this->triggering_file = $file;
        for ($j = 1; $j < $i; ++$j) {
            if (!isset($trace[$j]['function'], $trace[1 + $j]['class'], $trace[1 + $j]['args'][0])) {
                continue;
            }
            if ('trigger_error' === $trace[$j]['function'] && !isset($trace[$j]['class'])) {
                if (Debug_Class_Loader::class === $trace[1 + $j]['class']) {
                    $class = $trace[1 + $j]['args'][0];
                    $this->triggering_file = isset($trace[1 + $j]['args'][1]) ? realpath($trace[1 + $j]['args'][1]) : (new \ReflectionClass($class))->get_file_name();
                    $this->get_original_files_stack();
                    array_splice($this->original_files_stack, 0, $j, [$this->triggering_file]);
                    if (preg_match('/(?|"([^"]++)" that is deprecated|should implement method "(?:static )?([^:]++))/', $this->message, $m) || !str_contains($this->message, '()" will return') && !str_contains($this->message, 'native return type declaration') && preg_match('/^(?:The|Method) "([^":]++)/', $this->message, $m)) {
                        $this->triggering_file = (new \ReflectionClass($m[1]))->get_file_name();
                        array_unshift($this->original_files_stack, $this->triggering_file);
                    }
                }
                break;
            }
        }
        if (!isset($line['object']) && !isset($line['class'])) {
            return;
        }
        set_error_handler(static function (): void {
        });
        try {
            $parsed_msg = unserialize($this->message);
        } finally {
            restore_error_handler();
        }
        if ($parsed_msg && isset($parsed_msg['deprecation'])) {
            $this->message = $parsed_msg['deprecation'];
            $this->origin_class = $parsed_msg['class'];
            $this->origin_method = $parsed_msg['method'];
            if (isset($parsed_msg['files_stack'])) {
                $this->original_files_stack = $parsed_msg['files_stack'];
            }
            // If the deprecation has been triggered via
            // \Symfony\Bridge\PhpUnit\Legacy\SymfonyTestsListenerTrait::endTest()
            // then we need to use the serialized information to determine
            // if the error has been triggered from vendor code.
            if (isset($parsed_msg['triggering_file'])) {
                $this->triggering_file = $parsed_msg['triggering_file'];
            }
            return;
        }
        if (!isset($line['class'], $trace[$i - 2]['function']) || !str_starts_with($line['class'], Symfony_Tests_Listener_For::class)) {
            $this->origin_class = isset($line['object']) ? $line['object']::class : $line['class'];
            $this->origin_method = $line['function'];
            return;
        }
        $test = $line['args'][0] ?? null;
        if (($test instanceof Test_Case || $test instanceof Test_Suite) && ('trigger_error' !== $trace[$i - 2]['function'] || isset($trace[$i - 2]['class']))) {
            $this->origin_class = $test::class;
            $this->origin_method = $test->get_name();
        }
    }
    private function line_should_be_skipped(array $line): bool
    {
        if (!isset($line['class'])) {
            return true;
        }
        $class = $line['class'];
        return 'ReflectionMethod' === $class || str_starts_with($class, 'PHPUnit\\');
    }
    public function originates_from_debug_class_loader(): bool
    {
        return isset($this->triggering_class);
    }
    public function triggering_class(): string
    {
        if (null === $this->triggering_class) {
            throw new \LogicException('Check with originatesFromDebugClassLoader() before calling this method.');
        }
        return $this->triggering_class;
    }
    public function originates_from_an_object(): bool
    {
        return isset($this->origin_class);
    }
    public function originating_class(): string
    {
        if (null === $this->origin_class) {
            throw new \LogicException('Check with originatesFromAnObject() before calling this method.');
        }
        $class = $this->origin_class;
        return str_contains($class, "@anonymous\x00") ? ((get_parent_class($class) ?: key(class_implements($class))) ?: 'class') . '@anonymous' : $class;
    }
    public function originating_method(): string
    {
        if (null === $this->origin_method) {
            throw new \LogicException('Check with originatesFromAnObject() before calling this method.');
        }
        return $this->origin_method;
    }
    public function get_message(): string
    {
        return $this->message;
    }
    public function is_legacy(): bool
    {
        if (!$this->origin_class || (new \ReflectionClass($this->origin_class))->is_internal()) {
            return false;
        }
        $method = $this->originating_method();
        $groups = class_exists(Groups::class, false) ? [new Groups(), 'groups'] : [Test::class, 'getGroups'];
        return str_starts_with($method, 'testLegacy') || str_starts_with($method, 'provideLegacy') || str_starts_with($method, 'getLegacy') || strpos((string) $this->origin_class, '\Legacy') || \in_array('legacy', $groups($this->origin_class, $method), true);
    }
    public function is_muted(): bool
    {
        if ('Function ReflectionType::__toString() is deprecated' !== $this->message) {
            return false;
        }
        if (isset($this->trace[1]['class'])) {
            return str_starts_with($this->trace[1]['class'], 'PHPUnit\\');
        }
        return str_contains($this->triggering_file, \DIRECTORY_SEPARATOR . 'vendor' . \DIRECTORY_SEPARATOR . 'phpunit' . \DIRECTORY_SEPARATOR);
    }
    /**
     * Tells whether both the calling package and the called package are vendor
     * packages.
     */
    public function get_type(): string
    {
        $path_type = $this->get_path_type($this->triggering_file);
        if ($this->language_deprecation && self::PATH_TYPE_VENDOR === $path_type) {
            // the triggering file must be used for language deprecations
            return self::TYPE_INDIRECT;
        }
        if (self::PATH_TYPE_SELF === $path_type) {
            return self::TYPE_SELF;
        }
        if (self::PATH_TYPE_UNDETERMINED === $path_type) {
            return self::TYPE_UNDETERMINED;
        }
        $erroring_file = $erroring_package = null;
        foreach ($this->get_original_files_stack() as $file) {
            if ('-' === $file) {
                continue;
            }
            if ('Standard input code' === $file) {
                continue;
            }
            if (!realpath($file)) {
                continue;
            }
            if (self::PATH_TYPE_SELF === $path_type = $this->get_path_type($file)) {
                return self::TYPE_DIRECT;
            }
            if (self::PATH_TYPE_UNDETERMINED === $path_type) {
                return self::TYPE_UNDETERMINED;
            }
            if (null !== $erroring_file && null !== $erroring_package) {
                $package = $this->get_package($file);
                if ('composer' !== $package && $package !== $erroring_package) {
                    return self::TYPE_INDIRECT;
                }
                continue;
            }
            $erroring_file = $file;
            $erroring_package = $this->get_package($file);
        }
        return self::TYPE_DIRECT;
    }
    private function get_original_files_stack()
    {
        if (null === $this->original_files_stack) {
            $this->original_files_stack = [];
            foreach ($this->trace as $frame) {
                if (!isset($frame['file'], $frame['function'])) {
                    continue;
                }
                if (!isset($frame['class']) && \in_array($frame['function'], ['require', 'require_once', 'include', 'include_once'], true)) {
                    continue;
                }
                $this->original_files_stack[] = $frame['file'];
            }
        }
        return $this->original_files_stack;
    }
    /**
     * getPathType() should always be called prior to calling this method.
     */
    private function get_package(string $path): string
    {
        $path = realpath($path) ?: $path;
        foreach (self::get_vendors() as $vendor_root) {
            if (str_starts_with($path, $vendor_root)) {
                $relative_path = substr($path, \strlen($vendor_root) + 1);
                $vendor = strstr($relative_path, \DIRECTORY_SEPARATOR, true);
                if (false === $vendor) {
                    return 'symfony';
                }
                return rtrim($vendor . '/' . strstr(substr($relative_path, \strlen($vendor) + 1), \DIRECTORY_SEPARATOR, true), '/');
            }
        }
        throw new \RuntimeException(\sprintf('No vendors found for path "%s".', $path));
    }
    /**
     * @return string[]
     */
    private static function get_vendors(): array
    {
        if (null === self::$vendors) {
            self::$vendors = $paths = [];
            self::$vendors[] = \dirname(__DIR__) . \DIRECTORY_SEPARATOR . 'Legacy';
            if (class_exists(Debug_Class_Loader::class, false)) {
                self::$vendors[] = \dirname((new \ReflectionClass(Debug_Class_Loader::class))->get_file_name());
            }
            foreach (get_declared_classes() as $class) {
                if ('C' === $class[0] && str_starts_with($class, 'ComposerAutoloaderInit')) {
                    $r = new \ReflectionClass($class);
                    $v = \dirname($r->get_file_name(), 2);
                    if (file_exists($v . '/composer/installed.json')) {
                        self::$vendors[] = $v;
                        $loader = require $v . '/autoload.php';
                        $paths = self::add_source_paths_from_prefixes(array_merge($loader->get_prefixes(), $loader->get_prefixes_psr4()), $paths);
                        $paths = self::add_source_paths_from_prefixes(['fallback' => $loader->get_fallback_dirs(), 'fallback_psr4' => $loader->get_fallback_dirs_psr4()], $paths);
                    }
                }
            }
            foreach ($paths as $path) {
                foreach (self::$vendors as $vendor) {
                    if (!str_starts_with((string) $path, $vendor)) {
                        self::$internal_paths[] = $path;
                    }
                }
            }
        }
        return self::$vendors;
    }
    private static function add_source_paths_from_prefixes(array $prefixes_by_namespace, array $paths): array
    {
        foreach ($prefixes_by_namespace as $prefixes) {
            foreach ($prefixes as $prefix) {
                if (false !== realpath($prefix)) {
                    $paths[] = realpath($prefix);
                }
            }
        }
        return $paths;
    }
    private function get_path_type(string $path): string
    {
        $real_path = realpath($path);
        if (false === $real_path && '-' !== $path && 'Standard input code' !== $path) {
            return self::PATH_TYPE_UNDETERMINED;
        }
        foreach (self::get_vendors() as $vendor) {
            if (str_starts_with($real_path, $vendor) && false !== strpbrk(substr($real_path, \strlen($vendor), 1), '/' . \DIRECTORY_SEPARATOR)) {
                return self::PATH_TYPE_VENDOR;
            }
        }
        foreach (self::$internal_paths as $internal_path) {
            if (str_starts_with($real_path, $internal_path)) {
                return self::PATH_TYPE_SELF;
            }
        }
        return self::PATH_TYPE_UNDETERMINED;
    }
    public function to_string(): string
    {
        $exception = new \Exception($this->message);
        $reflection = new \ReflectionProperty($exception, 'trace');
        $reflection->set_value($exception, $this->trace);
        return ($this->originates_from_an_object() ? 'deprecation triggered by ' . $this->originating_class() . '::' . $this->originating_method() . ":\n" : '') . $this->message . "\n" . "Stack trace:\n" . str_replace(' ' . getcwd() . \DIRECTORY_SEPARATOR, ' ', $exception->get_trace_as_string()) . "\n";
    }
}