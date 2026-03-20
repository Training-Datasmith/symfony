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
namespace Symfony\Bridge\Php_Unit\Legacy;

use Php_Unit\Framework\Assertion_Failed_Error;
use Php_Unit\Framework\Data_Provider_Test_Suite;
use Php_Unit\Framework\Risky_Test_Error;
use Php_Unit\Framework\Test_Case;
use Php_Unit\Framework\Test_Suite;
use Php_Unit\Runner\Base_Test_Runner;
use Php_Unit\Runner\Phpt_Test_Case;
use Php_Unit\Util\Blacklist;
use Php_Unit\Util\Exclude_List;
use Php_Unit\Util\Test;
use Symfony\Bridge\Php_Unit\Clock_Mock;
use Symfony\Bridge\Php_Unit\Dns_Mock;
use Symfony\Bridge\Php_Unit\Expect_Deprecation_Trait;
use Symfony\Component\Error_Handler\Debug_Class_Loader;
/**
 * PHP 5.3 compatible trait-like shared implementation.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 *
 * @internal
 */
class Symfony_Tests_Listener_Trait
{
    public static $expected_deprecations = [];
    public static $previous_error_handler;
    private static $gathered_deprecations = [];
    private static bool $globally_enabled = false;
    private int $state = -1;
    private string|bool $skipped_file = false;
    private $was_skipped = [];
    private array $is_skipped = [];
    private $runs_in_separate_process = false;
    private $check_num_assertions = false;
    /**
     * @param array $mockedNamespaces List of namespaces, indexed by mocked features (time-sensitive or dns-sensitive)
     */
    public function __construct(array $mocked_namespaces = [])
    {
        setlocale(\LC_ALL, $_ENV['SYMFONY_PHPUNIT_LOCALE'] ?? 'C');
        if (class_exists(Exclude_List::class)) {
            (new Exclude_List())->get_excluded_directories();
            Exclude_List::add_directory(\dirname((new \ReflectionClass(self::class))->get_file_name(), 2));
        } elseif (method_exists(Blacklist::class, 'addDirectory')) {
            (new Blacklist())->get_blacklisted_directories();
            Blacklist::add_directory(\dirname((new \ReflectionClass(self::class))->get_file_name(), 2));
        } else {
            Blacklist::$blacklisted_class_names[self::class] = 2;
        }
        $enable_debug_class_loader = class_exists(Debug_Class_Loader::class);
        foreach ($mocked_namespaces as $type => $namespaces) {
            if (!\is_array($namespaces)) {
                $namespaces = [$namespaces];
            }
            if ('time-sensitive' === $type) {
                foreach ($namespaces as $ns) {
                    Clock_Mock::register($ns . '\DummyClass');
                }
            }
            if ('dns-sensitive' === $type) {
                foreach ($namespaces as $ns) {
                    Dns_Mock::register($ns . '\DummyClass');
                }
            }
            if ('debug-class-loader' === $type) {
                $enable_debug_class_loader = $namespaces && $namespaces[0];
            }
        }
        if ($enable_debug_class_loader) {
            Debug_Class_Loader::enable();
        }
        if (self::$globally_enabled) {
            $this->state = -2;
        } else {
            self::$globally_enabled = true;
        }
    }
    public function __serialize(): array
    {
        throw new \BadMethodCallException('Cannot serialize ' . self::class);
    }
    public function __unserialize(array $data): void
    {
        throw new \BadMethodCallException('Cannot unserialize ' . self::class);
    }
    public function __destruct()
    {
        if (0 < $this->state) {
            file_put_contents($this->skipped_file, '<?php return ' . var_export($this->is_skipped, true) . ';');
        }
    }
    public function global_listener_disabled(): void
    {
        self::$globally_enabled = false;
        $this->state = -1;
    }
    public function start_test_suite($suite): void
    {
        $suite_name = $suite->get_name();
        foreach ($suite->tests() as $test) {
            if (!$test instanceof Test_Case) {
                continue;
            }
            if (null === Test::get_preserve_global_state_settings($test::class, $test->get_name(false))) {
                $test->set_preserve_global_state(false);
            }
        }
        if (-1 === $this->state) {
            echo "Testing {$suite_name}\n";
            $this->state = 0;
            if ($this->skipped_file = getenv('SYMFONY_PHPUNIT_SKIPPED_TESTS')) {
                $this->state = 1;
                if (file_exists($this->skipped_file)) {
                    $this->state = 2;
                    if (!$this->was_skipped = require $this->skipped_file) {
                        echo "All tests already ran successfully.\n";
                        $suite->set_tests([]);
                    }
                }
            }
            $test_suites = [$suite];
            for ($i = 0; isset($test_suites[$i]); ++$i) {
                foreach ($test_suites[$i]->tests() as $test) {
                    if ($test instanceof Test_Suite) {
                        if (!class_exists($test->get_name(), false)) {
                            $test_suites[] = $test;
                            continue;
                        }
                        $groups = Test::get_groups($test->get_name());
                        if (\in_array('time-sensitive', $groups, true)) {
                            Clock_Mock::register($test->get_name());
                        }
                        if (\in_array('dns-sensitive', $groups, true)) {
                            Dns_Mock::register($test->get_name());
                        }
                    }
                }
            }
        } elseif (2 === $this->state) {
            $suites = [$suite];
            $skipped = [];
            while ($s = array_shift($suites)) {
                foreach ($s->tests() as $test) {
                    if ($test instanceof Test_Suite) {
                        $suites[] = $test;
                        continue;
                    }
                    if ($test instanceof Test_Case && isset($this->was_skipped[$test::class][$test->get_name()])) {
                        $skipped[] = $test;
                    }
                }
            }
            $suite->set_tests($skipped);
        }
    }
    public function add_skipped_test($test, \Exception $e, $time): void
    {
        if (0 < $this->state) {
            if ($test instanceof Data_Provider_Test_Suite) {
                foreach ($test->tests() as $test_with_data_provider) {
                    $this->is_skipped[$test_with_data_provider::class][$test_with_data_provider->get_name()] = 1;
                }
            } else {
                $this->is_skipped[$test::class][$test->get_name()] = 1;
            }
        }
    }
    public function start_test($test): void
    {
        if (-2 < $this->state && $test instanceof Phpt_Test_Case) {
            $this->runs_in_separate_process = tempnam(sys_get_temp_dir(), 'deprec');
            putenv('SYMFONY_DEPRECATIONS_SERIALIZE=' . $this->runs_in_separate_process);
            putenv('SYMFONY_EXPECTED_DEPRECATIONS_SERIALIZE=' . tempnam(sys_get_temp_dir(), 'expectdeprec'));
        }
        if (-2 < $this->state && $test instanceof Test_Case) {
            // This event is triggered before the test is re-run in isolation
            if ($this->will_be_isolated($test)) {
                $this->runs_in_separate_process = tempnam(sys_get_temp_dir(), 'deprec');
                putenv('SYMFONY_DEPRECATIONS_SERIALIZE=' . $this->runs_in_separate_process);
                putenv('SYMFONY_EXPECTED_DEPRECATIONS_SERIALIZE=' . tempnam(sys_get_temp_dir(), 'expectdeprec'));
            }
            $groups = Test::get_groups($test::class, $test->get_name(false));
            if (!$this->runs_in_separate_process) {
                if (\in_array('time-sensitive', $groups, true)) {
                    Clock_Mock::register($test::class);
                    Clock_Mock::with_clock_mock(true);
                }
                if (\in_array('dns-sensitive', $groups, true)) {
                    Dns_Mock::register($test::class);
                }
            }
            if (!$test->get_test_result_object()) {
                return;
            }
            $annotations = Test::parse_test_method_annotations($test::class, $test->get_name(false));
            if (isset($annotations['class']['expectedDeprecation'])) {
                $test->get_test_result_object()->add_error($test, new Assertion_Failed_Error('"@expectedDeprecation" annotations are not allowed at the class level.'), 0);
            }
            if (isset($annotations['method']['expectedDeprecation']) || $this->check_num_assertions = method_exists($test, 'expectDeprecation') && (new \ReflectionMethod($test, 'expectDeprecation'))->get_file_name() === (new \ReflectionMethod(Expect_Deprecation_Trait::class, 'expectDeprecation'))->get_file_name()) {
                if (isset($annotations['method']['expectedDeprecation'])) {
                    self::$expected_deprecations = $annotations['method']['expectedDeprecation'];
                    self::$previous_error_handler = set_error_handler(self::handle_error(...));
                    @trigger_error('Since symfony/phpunit-bridge 5.1: Using "@expectedDeprecation" annotations in tests is deprecated, use the "ExpectDeprecationTrait::expectDeprecation()" method instead.', \E_USER_DEPRECATED);
                }
                if ($this->check_num_assertions) {
                    $this->check_num_assertions = $test->get_test_result_object()->is_strict_about_tests_that_do_not_test_anything();
                }
                $test->get_test_result_object()->be_strict_about_tests_that_do_not_test_anything(false);
            }
        }
    }
    public function end_test($test, $time): void
    {
        if ($file = getenv('SYMFONY_EXPECTED_DEPRECATIONS_SERIALIZE')) {
            putenv('SYMFONY_EXPECTED_DEPRECATIONS_SERIALIZE');
            $expected_deprecations = file_get_contents($file);
            if ($expected_deprecations) {
                self::$expected_deprecations = array_merge(self::$expected_deprecations, unserialize($expected_deprecations));
                if (!self::$previous_error_handler) {
                    self::$previous_error_handler = set_error_handler(self::handle_error(...));
                }
            }
        }
        if (class_exists(Debug_Class_Loader::class, false)) {
            Debug_Class_Loader::check_classes();
        }
        $class_name = $test::class;
        $groups = Test::get_groups($class_name, $test->get_name(false));
        if ($this->check_num_assertions) {
            $assertions = \count(self::$expected_deprecations) + $test->get_num_assertions();
            if ($test instanceof Test_Case && $test->does_not_perform_assertions() && $assertions > 0) {
                $test->get_test_result_object()->add_failure($test, new Risky_Test_Error(\sprintf('This test is annotated with "@doesNotPerformAssertions", but performed %s assertions', $assertions)), $time);
            } elseif ($test instanceof Test_Case && 0 === $assertions && !$test->does_not_perform_assertions() && $test->get_test_result_object()->none_skipped()) {
                $test->get_test_result_object()->add_failure($test, new Risky_Test_Error('This test did not perform any assertions'), $time);
            }
            $this->check_num_assertions = false;
        }
        if ($this->runs_in_separate_process) {
            $deprecations = file_get_contents($this->runs_in_separate_process);
            unlink($this->runs_in_separate_process);
            putenv('SYMFONY_DEPRECATIONS_SERIALIZE');
            foreach ($deprecations ? unserialize($deprecations) : [] as $deprecation) {
                $error = serialize(['deprecation' => $deprecation[1], 'class' => $class_name, 'method' => $test->get_name(false), 'triggering_file' => $deprecation[2] ?? null, 'files_stack' => $deprecation[3] ?? []]);
                if ($deprecation[0]) {
                    // unsilenced on purpose
                    trigger_error($error, \E_USER_DEPRECATED);
                } else {
                    @trigger_error($error, \E_USER_DEPRECATED);
                }
            }
            $this->runs_in_separate_process = false;
        }
        if (self::$expected_deprecations) {
            if ($test instanceof Test_Case && !\in_array($test->get_status(), [Base_Test_Runner::STATUS_SKIPPED, Base_Test_Runner::STATUS_INCOMPLETE], true)) {
                $test->add_to_assertion_count(\count(self::$expected_deprecations));
            }
            restore_error_handler();
            if ($test instanceof Test_Case && !\in_array('legacy', $groups, true)) {
                $test->get_test_result_object()->add_error($test, new Assertion_Failed_Error('Only tests with the "@group legacy" annotation can expect a deprecation.'), 0);
            } elseif ($test instanceof Test_Case && !\in_array($test->get_status(), [Base_Test_Runner::STATUS_SKIPPED, Base_Test_Runner::STATUS_INCOMPLETE, Base_Test_Runner::STATUS_FAILURE, Base_Test_Runner::STATUS_ERROR], true)) {
                try {
                    $prefix = "@expectedDeprecation:\n";
                    $test->assert_string_matches_format($prefix . '%A  ' . implode("\n%A  ", self::$expected_deprecations) . "\n%A", $prefix . '  ' . implode("\n  ", self::$gathered_deprecations) . "\n");
                } catch (Assertion_Failed_Error $e) {
                    $test->get_test_result_object()->add_failure($test, $e, $time);
                }
            }
            self::$expected_deprecations = self::$gathered_deprecations = [];
            self::$previous_error_handler = null;
        }
        if (!$this->runs_in_separate_process && -2 < $this->state && $test instanceof Test_Case) {
            if (\in_array('time-sensitive', $groups, true)) {
                Clock_Mock::with_clock_mock(false);
            }
            if (\in_array('dns-sensitive', $groups, true)) {
                Dns_Mock::with_mocked_hosts([]);
            }
        }
    }
    public static function handle_error($type, $msg, $file, $line, $context = [])
    {
        if (\E_USER_DEPRECATED !== $type && \E_DEPRECATED !== $type) {
            $h = self::$previous_error_handler;
            return $h ? $h($type, $msg, $file, $line, $context) : false;
        }
        // If the message is serialized we need to extract the message. This occurs when the error is triggered
        // by the isolated test path in \Symfony\Bridge\PhpUnit\Legacy\SymfonyTestsListenerTrait::endTest().
        $parsed_msg = @unserialize($msg);
        if (\is_array($parsed_msg)) {
            $msg = $parsed_msg['deprecation'];
        }
        if (error_reporting() & $type) {
            $msg = 'Unsilenced deprecation: ' . $msg;
        }
        self::$gathered_deprecations[] = $msg;
        return true;
    }
    private function will_be_isolated(Test_Case $test): bool
    {
        if ($test->is_in_isolation()) {
            return false;
        }
        $r = new \ReflectionProperty($test, 'runTestInSeparateProcess');
        return $r->get_value($test) ?? false;
    }
}