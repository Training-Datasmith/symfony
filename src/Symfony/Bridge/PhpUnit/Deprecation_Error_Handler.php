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
namespace Symfony\Bridge\Php_Unit;

use Php_Unit\Framework\Test_Case;
use Php_Unit\Framework\Test_Result;
use Php_Unit\Runner\Error_Handler;
use Php_Unit\Util\Error\Handler;
use Php_Unit\Util\Error_Handler as UtilErrorHandler;
use Symfony\Bridge\Php_Unit\Deprecation_Error_Handler\Configuration;
use Symfony\Bridge\Php_Unit\Deprecation_Error_Handler\Deprecation;
use Symfony\Bridge\Php_Unit\Deprecation_Error_Handler\Deprecation_Group;
use Symfony\Component\Error_Handler\Debug_Class_Loader;
/**
 * Catch deprecation notices and print a summary report at the end of the test suite.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
class Deprecation_Error_Handler
{
    public const MODE_DISABLED = 'disabled';
    public const MODE_WEAK = 'max[total]=999999&verbose=0';
    public const MODE_STRICT = 'max[total]=0';
    private $mode;
    private ?\Symfony\Bridge\Php_Unit\Deprecation_Error_Handler\Configuration $configuration = null;
    /**
     * @var DeprecationGroup[]
     */
    private array $deprecation_groups = [];
    private static bool $is_registered = false;
    private static ?string $error_handler = null;
    public function __construct()
    {
        $this->reset_deprecation_groups();
    }
    /**
     * Registers and configures the deprecation handler.
     *
     * The mode is a query string with options:
     *  - "disabled" to enable/disable the deprecation handler
     *  - "verbose" to enable/disable displaying the deprecation report
     *  - "quiet" to disable displaying the deprecation report only for some groups (i.e. quiet[]=other)
     *  - "max" to configure the number of deprecations to allow before exiting with a non-zero
     *    status code; it's an array with keys "total", "self", "direct" and "indirect"
     *
     * The default mode is "max[total]=0&verbose=1".
     *
     * The mode can alternatively be "/some-regexp/" to stop the test suite whenever
     * a deprecation message matches the given regular expression.
     *
     * @param int|string|false $mode The reporting mode, defaults to not allowing any deprecations
     */
    public static function register($mode = 0): void
    {
        if (self::$is_registered) {
            return;
        }
        $handler = new self();
        $old_error_handler = set_error_handler($handler->handle_error(...));
        if (null !== $old_error_handler) {
            restore_error_handler();
            if ($old_error_handler instanceof Util_Error_Handler || [Util_Error_Handler::class, 'handleError'] === $old_error_handler || $old_error_handler instanceof Error_Handler || [Error_Handler::class, 'handleError'] === $old_error_handler) {
                restore_error_handler();
                self::register($mode);
            }
        } else {
            $handler->mode = $mode;
            self::$is_registered = true;
            register_shutdown_function([$handler, 'shutdown']);
        }
    }
    public static function collect_deprecations($output_file): void
    {
        $deprecations = [];
        $previous_error_handler = set_error_handler(static function ($type, $msg, $file, $line, $context = []) use (&$deprecations, &$previous_error_handler) {
            if (\E_USER_DEPRECATED !== $type && \E_DEPRECATED !== $type && (\E_WARNING !== $type || !str_contains($msg, '" targeting switch is equivalent to "break'))) {
                if ($previous_error_handler) {
                    return $previous_error_handler($type, $msg, $file, $line, $context);
                }
                return \call_user_func(self::get_php_unit_error_handler(), $type, $msg, $file, $line, $context);
            }
            $files_stack = [];
            foreach (debug_backtrace() as $frame) {
                if (!isset($frame['file'])) {
                    continue;
                }
                if (\in_array($frame['function'], ['require', 'require_once', 'include', 'include_once'], true)) {
                    continue;
                }
                $files_stack[] = $frame['file'];
            }
            $deprecations[] = [error_reporting() & $type, $msg, $file, $files_stack];
            return null;
        });
        register_shutdown_function(static function () use ($output_file, &$deprecations): void {
            file_put_contents($output_file, serialize($deprecations));
        });
    }
    /**
     * @internal
     */
    public function handle_error($type, $msg, $file, $line, $context = [])
    {
        if (\E_USER_DEPRECATED !== $type && \E_DEPRECATED !== $type && (\E_WARNING !== $type || !str_contains((string) $msg, '" targeting switch is equivalent to "break')) || !$this->get_configuration()->is_enabled()) {
            return \call_user_func(self::get_php_unit_error_handler(), $type, $msg, $file, $line, $context);
        }
        $trace = debug_backtrace();
        if (isset($trace[1]['function'], $trace[1]['args'][0]) && ('trigger_error' === $trace[1]['function'] || 'user_error' === $trace[1]['function'])) {
            $msg = $trace[1]['args'][0];
        }
        $deprecation = new Deprecation($msg, $trace, $file, \E_DEPRECATED === $type);
        if ($deprecation->is_muted()) {
            return null;
        }
        if ($this->get_configuration()->is_ignored_deprecation($deprecation)) {
            return null;
        }
        if ($this->get_configuration()->is_baseline_deprecation($deprecation)) {
            return null;
        }
        $msg = $deprecation->get_message();
        if (\E_DEPRECATED !== $type && error_reporting() & $type) {
            $group = 'unsilenced';
        } elseif ($deprecation->is_legacy()) {
            $group = 'legacy';
        } else {
            $group = [Deprecation::TYPE_SELF => 'self', Deprecation::TYPE_DIRECT => 'direct', Deprecation::TYPE_INDIRECT => 'indirect', Deprecation::TYPE_UNDETERMINED => 'other'][$deprecation->get_type()];
        }
        if ($this->get_configuration()->should_display_stack_trace($msg)) {
            echo "\n" . ucfirst($group) . ' ' . $deprecation->to_string();
            exit(1);
        }
        if (\PHP_VERSION_ID >= 80500 && \in_array($msg, ['The __sleep() serialization magic method has been deprecated. Implement __serialize() instead (or in addition, if support for old PHP versions is necessary)', 'The __wakeup() serialization magic method has been deprecated. Implement __unserialize() instead (or in addition, if support for old PHP versions is necessary)'], true)) {
            return null;
        }
        if ('legacy' === $group) {
            $this->deprecation_groups[$group]->add_notice();
        } elseif ($deprecation->originates_from_an_object()) {
            $class = $deprecation->originating_class();
            $method = $deprecation->originating_method();
            $this->deprecation_groups[$group]->add_notice_from_object($msg, $class, $method);
        } else {
            $this->deprecation_groups[$group]->add_notice_from_procedural_code($msg);
        }
        return null;
    }
    /**
     * @internal
     */
    public function shutdown(): void
    {
        $configuration = $this->get_configuration();
        if ($configuration->is_in_regex_mode()) {
            return;
        }
        if (class_exists(Debug_Class_Loader::class, false)) {
            Debug_Class_Loader::check_classes();
        }
        $curr_error_handler = set_error_handler(is_int(...));
        restore_error_handler();
        if ($curr_error_handler !== $this->handle_error(...)) {
            echo "\n", self::colorize('THE ERROR HANDLER HAS CHANGED!', true), "\n";
        }
        $groups = array_keys($this->deprecation_groups);
        // store failing status
        $is_failing = !$configuration->tolerates($this->deprecation_groups);
        $this->display_deprecations($groups, $configuration);
        $this->reset_deprecation_groups();
        register_shutdown_function(function () use ($is_failing, $groups, $configuration): void {
            foreach ($this->deprecation_groups as $group) {
                if ($group->count() > 0) {
                    echo "Shutdown-time deprecations:\n";
                    break;
                }
            }
            $is_failing_at_shutdown = !$configuration->tolerates($this->deprecation_groups);
            $this->display_deprecations($groups, $configuration);
            if ($configuration->is_generating_baseline()) {
                $configuration->write_baseline();
            }
            if ($is_failing || $is_failing_at_shutdown) {
                exit(1);
            }
        });
    }
    private function reset_deprecation_groups(): void
    {
        $this->deprecation_groups = ['unsilenced' => new Deprecation_Group(), 'self' => new Deprecation_Group(), 'direct' => new Deprecation_Group(), 'indirect' => new Deprecation_Group(), 'legacy' => new Deprecation_Group(), 'other' => new Deprecation_Group()];
    }
    private function get_configuration()
    {
        if (null !== $this->configuration) {
            return $this->configuration;
        }
        if (false === $mode = $this->mode) {
            $mode = $_SERVER['SYMFONY_DEPRECATIONS_HELPER'] ?? $_ENV['SYMFONY_DEPRECATIONS_HELPER'] ?? getenv('SYMFONY_DEPRECATIONS_HELPER');
        }
        if ('strict' === $mode) {
            return $this->configuration = Configuration::in_strict_mode();
        }
        if (self::MODE_DISABLED === $mode) {
            return $this->configuration = Configuration::in_disabled_mode();
        }
        if ('weak' === $mode) {
            return $this->configuration = Configuration::in_weak_mode();
        }
        if (isset($mode[0]) && '/' === $mode[0]) {
            return $this->configuration = Configuration::from_regex($mode);
        }
        if (preg_match('/^[1-9][0-9]*$/', (string) $mode)) {
            return $this->configuration = Configuration::from_number($mode);
        }
        if (!$mode) {
            return $this->configuration = Configuration::from_number(0);
        }
        return $this->configuration = Configuration::from_url_encoded_string((string) $mode);
    }
    private static function colorize(string $str, bool $red): string
    {
        if (!self::has_color_support()) {
            return $str;
        }
        $color = $red ? '41;37' : '43;30';
        return "\x1b[{$color}m{$str}\x1b[0m";
    }
    /**
     * @param string[] $groups
     */
    private function display_deprecations(array $groups, Configuration $configuration): void
    {
        $cmp = static fn($a, $b): int|float => $b->count() - $a->count();
        if ($configuration->should_write_to_log_file()) {
            if (false === $handle = @fopen($file = $configuration->get_log_file(), 'a')) {
                throw new \InvalidArgumentException(\sprintf('The configured log file "%s" is not writeable.', $file));
            }
        } else {
            $handle = fopen('php://output', 'w');
        }
        foreach ($groups as $group) {
            if ($this->deprecation_groups[$group]->count()) {
                $deprecation_group_message = \sprintf('%s deprecation notices (%d)', \in_array($group, ['direct', 'indirect', 'self'], true) ? "Remaining {$group}" : ucfirst($group), $this->deprecation_groups[$group]->count());
                if ($configuration->should_write_to_log_file()) {
                    fwrite($handle, "\n{$deprecation_group_message}\n");
                } else {
                    fwrite($handle, "\n" . self::colorize($deprecation_group_message, 'legacy' !== $group && 'indirect' !== $group) . "\n");
                }
                // Skip the verbose output if the group is quiet and not failing according to its threshold:
                if ('legacy' !== $group && !$configuration->verbose_output($group) && $configuration->tolerates_for_group($group, $this->deprecation_groups)) {
                    continue;
                }
                $notices = $this->deprecation_groups[$group]->notices();
                uasort($notices, $cmp);
                foreach ($notices as $msg => $notice) {
                    fwrite($handle, \sprintf("\n  %sx: %s\n", $notice->count(), $msg));
                    $counts_by_caller = $notice->get_counts_by_caller();
                    arsort($counts_by_caller);
                    $limit = 5;
                    foreach ($counts_by_caller as $method => $count) {
                        if ('count' !== $method) {
                            if (!$limit--) {
                                fwrite($handle, "    ...\n");
                                break;
                            }
                            fwrite($handle, \sprintf("    %dx in %s\n", $count, preg_replace('/(.*)\\\\(.*?::.*?)$/', '$2 from $1', (string) $method)));
                        }
                    }
                }
            }
        }
        if (!empty($notices)) {
            fwrite($handle, "\n");
        }
    }
    private static function get_php_unit_error_handler(): callable
    {
        if (!$eh = self::$error_handler) {
            if (class_exists(Handler::class)) {
                $eh = self::$error_handler = Handler::class;
            } elseif (method_exists(Util_Error_Handler::class, '__invoke')) {
                $eh = self::$error_handler = Util_Error_Handler::class;
            } elseif (method_exists(Error_Handler::class, '__invoke')) {
                $eh = self::$error_handler = Error_Handler::class;
            } else {
                return self::$error_handler = 'PHPUnit\Util\ErrorHandler::handleError';
            }
        }
        if ('PHPUnit\Util\ErrorHandler::handleError' === $eh) {
            return $eh;
        }
        foreach (debug_backtrace(\DEBUG_BACKTRACE_PROVIDE_OBJECT | \DEBUG_BACKTRACE_IGNORE_ARGS) as $frame) {
            if (!isset($frame['object'])) {
                continue;
            }
            if ($frame['object'] instanceof Test_Result) {
                return new $eh($frame['object']->get_convert_deprecations_to_exceptions(), $frame['object']->get_convert_errors_to_exceptions(), $frame['object']->get_convert_notices_to_exceptions(), $frame['object']->get_convert_warnings_to_exceptions());
            }
            if (Error_Handler::class === $eh && $frame['object'] instanceof Test_Case) {
                return static function (int $error_number, string $error_string, string $error_file, int $error_line): true {
                    Error_Handler::instance()($error_number, $error_string, $error_file, $error_line);
                    return true;
                };
            }
        }
        return static fn(): false => false;
    }
    /**
     * Returns true if STDOUT is defined and supports colorization.
     *
     * Reference: Composer\XdebugHandler\Process::supportsColor
     * https://github.com/composer/xdebug-handler
     */
    private static function has_color_support(): bool
    {
        if (!\defined('STDOUT')) {
            return false;
        }
        // Follow https://no-color.org/
        if ('' !== (($_SERVER['NO_COLOR'] ?? getenv('NO_COLOR'))[0] ?? '')) {
            return false;
        }
        // Follow https://force-color.org/
        if ('' !== (($_SERVER['FORCE_COLOR'] ?? getenv('FORCE_COLOR'))[0] ?? '')) {
            return true;
        }
        // Detect msysgit/mingw and assume this is a tty because detection
        // does not work correctly, see https://github.com/composer/composer/issues/9690
        if (!@stream_isatty(\STDOUT) && !\in_array(strtoupper((string) getenv('MSYSTEM')), ['MINGW32', 'MINGW64'], true)) {
            return false;
        }
        if ('\\' === \DIRECTORY_SEPARATOR && @sapi_windows_vt100_support(\STDOUT)) {
            return true;
        }
        if ('Hyper' === getenv('TERM_PROGRAM') || false !== getenv('COLORTERM') || false !== getenv('ANSICON') || 'ON' === getenv('ConEmuANSI')) {
            return true;
        }
        if ('dumb' === $term = (string) getenv('TERM')) {
            return false;
        }
        // See https://github.com/chalk/supports-color/blob/d4f413efaf8da045c5ab440ed418ef02dbb28bf1/index.js#L157
        return preg_match('/^((screen|xterm|vt100|vt220|putty|rxvt|ansi|cygwin|linux).*)|(.*-256(color)?(-bce)?)$/', $term);
    }
}