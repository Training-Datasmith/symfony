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
// Please update when phpunit needs to be reinstalled with fresh deps:
// Cache-Id: 2021-02-04 11:00 UTC
if ('cli' !== \PHP_SAPI && 'phpdbg' !== \PHP_SAPI) {
    throw new Exception('This script must be run from the command line.');
}
error_reporting(-1);
global $argv, $argc;
$argv = $_SERVER['argv'] ?? [];
$argc = $_SERVER['argc'] ?? 0;
$get_env_var = static function (string $name, $default = false) use ($argv) {
    if (false !== $value = getenv($name)) {
        return $value;
    }
    static $phpunit_config = null;
    if (null === $phpunit_config) {
        $phpunit_config_filename = null;
        $get_php_unit_config = static function (?string $probable_config) use (&$get_php_unit_config) {
            if (!$probable_config) {
                return null;
            }
            if (is_dir($probable_config)) {
                return $get_php_unit_config($probable_config . \DIRECTORY_SEPARATOR . 'phpunit');
            }
            foreach (['.xml', '.xml.dist', '.dist.xml'] as $suffix) {
                if (file_exists($candidate = $probable_config . $suffix)) {
                    return $candidate;
                }
            }
            return null;
        };
        foreach ($argv as $cli_argument_index => $cli_argument) {
            if ('--' === $cli_argument) {
                break;
            }
            // long option
            if ('--configuration' === $cli_argument && array_key_exists($cli_argument_index + 1, $argv)) {
                $phpunit_config_filename = $get_php_unit_config($argv[$cli_argument_index + 1]);
                break;
            }
            // short option
            if (str_starts_with((string) $cli_argument, '-c')) {
                if ('-c' === $cli_argument && array_key_exists($cli_argument_index + 1, $argv)) {
                    $phpunit_config_filename = $get_php_unit_config($argv[$cli_argument_index + 1]);
                } else {
                    $phpunit_config_filename = $get_php_unit_config(substr((string) $cli_argument, 2));
                }
                break;
            }
        }
        $phpunit_config_filename = $phpunit_config_filename ?: $get_php_unit_config('phpunit');
        if ($phpunit_config_filename) {
            $phpunit_config = new Dom_Document();
            $phpunit_config->load($phpunit_config_filename);
        } else {
            $phpunit_config = false;
        }
    }
    if (false !== $phpunit_config) {
        $var = new Domx_Path($phpunit_config);
        foreach ($var->query('//php/server[@name="' . $name . '"]') as $var) {
            return $var->get_attribute('value');
        }
        foreach ($var->query('//php/env[@name="' . $name . '"]') as $var) {
            return $var->get_attribute('value');
        }
    }
    return $default;
};
$passthru_or_fail = static function ($command): void {
    passthru($command, $status);
    if ($status) {
        exit($status);
    }
};
$PHPUNIT_VERSION = $get_env_var('SYMFONY_PHPUNIT_VERSION', '9.6') ?: '9.6';
$MAX_PHPUNIT_VERSION = $get_env_var('SYMFONY_MAX_PHPUNIT_VERSION', false);
if ($MAX_PHPUNIT_VERSION && version_compare($MAX_PHPUNIT_VERSION, $PHPUNIT_VERSION, '<')) {
    $PHPUNIT_VERSION = $MAX_PHPUNIT_VERSION;
}
if (version_compare($PHPUNIT_VERSION, '10.0', '>=') && version_compare($PHPUNIT_VERSION, '11.0', '<')) {
    fwrite(\STDERR, 'This script does not work with PHPUnit 10.' . \PHP_EOL);
    exit(1);
}
$PHPUNIT_REMOVE_RETURN_TYPEHINT = filter_var($get_env_var('SYMFONY_PHPUNIT_REMOVE_RETURN_TYPEHINT', '0'), \FILTER_VALIDATE_BOOLEAN);
$COMPOSER_JSON = getenv('COMPOSER') ?: 'composer.json';
$root = __DIR__;
while (!file_exists($root . '/' . $COMPOSER_JSON) || file_exists($root . '/DeprecationErrorHandler.php')) {
    if ($root === dirname($root)) {
        break;
    }
    $root = dirname($root);
}
$old_pwd = getcwd();
$PHPUNIT_DIR = rtrim($get_env_var('SYMFONY_PHPUNIT_DIR', $root . '/vendor/bin/.phpunit'), '/' . \DIRECTORY_SEPARATOR);
$PHP = defined('PHP_BINARY') ? \PHP_BINARY : 'php';
$PHP = escapeshellarg($PHP);
if ('phpdbg' === \PHP_SAPI) {
    $PHP .= ' -qrr';
}
$default_envs = ['COMPOSER' => 'composer.json', 'COMPOSER_VENDOR_DIR' => 'vendor', 'COMPOSER_BIN_DIR' => 'bin', 'COMPOSER_NO_INTERACTION' => '1', 'SYMFONY_SIMPLE_PHPUNIT_BIN_DIR' => __DIR__];
foreach ($default_envs as $env_name => $env_value) {
    if ($env_value !== getenv($env_name)) {
        putenv("{$env_name}={$env_value}");
        $_SERVER[$env_name] = $_ENV[$env_name] = $env_value;
    }
}
if ('disabled' === $get_env_var('SYMFONY_DEPRECATIONS_HELPER') || version_compare($PHPUNIT_VERSION, '11.0', '>=')) {
    putenv('SYMFONY_DEPRECATIONS_HELPER=disabled');
}
if (!$get_env_var('DOCTRINE_DEPRECATIONS')) {
    putenv('DOCTRINE_DEPRECATIONS=trigger');
    $_SERVER['DOCTRINE_DEPRECATIONS'] = $_ENV['DOCTRINE_DEPRECATIONS'] = 'trigger';
}
$COMPOSER = ($COMPOSER = getenv('COMPOSER_BINARY')) || file_exists($COMPOSER = $old_pwd . '/composer.phar') || ($COMPOSER = rtrim((string) ('\\' === \DIRECTORY_SEPARATOR ? preg_replace('/[\r\n].*/', '', shell_exec('where.exe composer.phar 2> NUL')) : shell_exec('which composer.phar 2> /dev/null')))) || ($COMPOSER = rtrim((string) ('\\' === \DIRECTORY_SEPARATOR ? preg_replace('/[\r\n].*/', '', shell_exec('where.exe composer 2> NUL')) : shell_exec('which composer 2> /dev/null')))) || file_exists($COMPOSER = rtrim((string) ('\\' === \DIRECTORY_SEPARATOR ? shell_exec('git rev-parse --show-toplevel 2> NUL') : shell_exec('git rev-parse --show-toplevel 2> /dev/null'))) . \DIRECTORY_SEPARATOR . 'composer.phar') ? ('#!/usr/bin/env php' === file_get_contents($COMPOSER, false, null, 0, 18) ? $PHP : '') . ' ' . escapeshellarg($COMPOSER) : 'composer';
$prev_cache_dir = getenv('COMPOSER_CACHE_DIR');
if ($prev_cache_dir) {
    if (false === $absolute_cache_dir = realpath($prev_cache_dir)) {
        @mkdir($prev_cache_dir, 0777, true);
        $absolute_cache_dir = realpath($prev_cache_dir);
    }
    if ($absolute_cache_dir) {
        putenv("COMPOSER_CACHE_DIR={$absolute_cache_dir}");
    } else {
        $prev_cache_dir = false;
    }
}
$SYMFONY_PHPUNIT_REMOVE = $get_env_var('SYMFONY_PHPUNIT_REMOVE', 'phpspec/prophecy');
$SYMFONY_PHPUNIT_REQUIRE = $get_env_var('SYMFONY_PHPUNIT_REQUIRE', '');
$configuration_hash = md5(implode(\PHP_EOL, [md5_file(__FILE__), $SYMFONY_PHPUNIT_REMOVE, $SYMFONY_PHPUNIT_REQUIRE, (int) $PHPUNIT_REMOVE_RETURN_TYPEHINT]));
$PHPUNIT_VERSION_DIR = sprintf('phpunit-%s-%d', $PHPUNIT_VERSION, $PHPUNIT_REMOVE_RETURN_TYPEHINT);
if (!file_exists("{$PHPUNIT_DIR}/{$PHPUNIT_VERSION_DIR}/phpunit") || $configuration_hash !== @file_get_contents("{$PHPUNIT_DIR}/.{$PHPUNIT_VERSION_DIR}.md5")) {
    // Build a standalone phpunit without symfony/yaml nor prophecy by default
    @mkdir($PHPUNIT_DIR, 0777, true);
    chdir($PHPUNIT_DIR);
    if (file_exists("{$PHPUNIT_VERSION_DIR}")) {
        passthru(sprintf('\\' === \DIRECTORY_SEPARATOR ? 'rmdir /S /Q %s 2> NUL' : 'rm -rf %s', escapeshellarg("{$PHPUNIT_VERSION_DIR}.old")));
        rename("{$PHPUNIT_VERSION_DIR}", "{$PHPUNIT_VERSION_DIR}.old");
        passthru(sprintf('\\' === \DIRECTORY_SEPARATOR ? 'rmdir /S /Q %s' : 'rm -rf %s', escapeshellarg("{$PHPUNIT_VERSION_DIR}.old")));
    }
    $info = [];
    foreach (explode("\n", shell_exec("{$COMPOSER} info --no-ansi -a -n phpunit/phpunit \"{$PHPUNIT_VERSION}.*\"")) as $line) {
        $line = rtrim($line);
        if (!$info && preg_match('/^versions +: /', $line)) {
            $info['versions'] = explode(', ', ltrim(substr($line, 9), ': '));
        } elseif (isset($info['requires'])) {
            if ('' === $line) {
                break;
            }
            $line = explode(' ', $line, 2);
            $info['requires'][$line[0]] = $line[1];
        } elseif ($info && 'requires' === $line) {
            $info['requires'] = [];
        }
    }
    if (in_array('--colors=never', $argv, true) || isset($argv[$i = array_search('never', $argv, true) - 1]) && '--colors' === $argv[$i]) {
        $COMPOSER .= ' --no-ansi';
    } else {
        $COMPOSER .= ' --ansi';
    }
    $info += ['versions' => [], 'requires' => ['php' => '*']];
    $stable_versions = array_filter($info['versions'], static fn(string $v): bool => !preg_match('/-dev$|^dev-/', $v));
    if (!$stable_versions) {
        $passthru_or_fail("{$COMPOSER} create-project --ignore-platform-reqs --no-install --prefer-dist --no-scripts --no-plugins --no-progress -s dev phpunit/phpunit {$PHPUNIT_VERSION_DIR} \"{$PHPUNIT_VERSION}.*\"");
    } else {
        $passthru_or_fail("{$COMPOSER} create-project --ignore-platform-reqs --no-install --prefer-dist --no-scripts --no-plugins --no-progress phpunit/phpunit {$PHPUNIT_VERSION_DIR} \"{$PHPUNIT_VERSION}.*\"");
    }
    @copy("{$PHPUNIT_VERSION_DIR}/phpunit.xsd", 'phpunit.xsd');
    chdir("{$PHPUNIT_VERSION_DIR}");
    if ($SYMFONY_PHPUNIT_REMOVE) {
        $passthru_or_fail("{$COMPOSER} remove --no-update " . $SYMFONY_PHPUNIT_REMOVE);
    }
    if ($SYMFONY_PHPUNIT_REQUIRE) {
        $passthru_or_fail("{$COMPOSER} require --no-update " . $SYMFONY_PHPUNIT_REQUIRE);
    }
    if (preg_match('{\^((\d++\.)\d++)[\d\.]*$}', $info['requires']['php'], $php_version) && version_compare($php_version[2] . '99', \PHP_VERSION, '<')) {
        $passthru_or_fail("{$COMPOSER} config platform.php \"{$php_version[1]}.99\"");
    } else {
        $passthru_or_fail("{$COMPOSER} config --unset platform.php");
    }
    if (file_exists($path = $root . '/vendor/symfony/phpunit-bridge')) {
        $haystack = "{$PHPUNIT_DIR}/{$PHPUNIT_VERSION_DIR}";
        $root_len = strlen($root);
        $p = ($root_len <= strlen($haystack) ? str_repeat('../', substr_count($haystack, '/', $root_len)) : '') . 'vendor/symfony/phpunit-bridge';
        if (realpath($p) === realpath($path)) {
            $path = $p;
        }
        $passthru_or_fail("{$COMPOSER} require --no-update symfony/phpunit-bridge \"*@dev\"");
        $passthru_or_fail("{$COMPOSER} config repositories.phpunit-bridge path " . escapeshellarg(str_replace('/', \DIRECTORY_SEPARATOR, $path)));
        if ('\\' === \DIRECTORY_SEPARATOR) {
            file_put_contents('composer.json', preg_replace('/^( {8})"phpunit-bridge": \{$/m', "\$0\n\$1    " . '"options": {"symlink": false},', file_get_contents('composer.json')));
        }
    } else {
        $passthru_or_fail("{$COMPOSER} require --no-update symfony/phpunit-bridge \"*\"");
    }
    $prev_root = getenv('COMPOSER_ROOT_VERSION');
    putenv("COMPOSER_ROOT_VERSION={$PHPUNIT_VERSION}.99");
    // --no-suggest is not in the list to keep compat with composer 1.0, which is shipped with Ubuntu 16.04LTS
    $exit = proc_close(proc_open("{$COMPOSER} update --no-dev --prefer-dist --no-progress", [], $p, getcwd()));
    putenv('COMPOSER_ROOT_VERSION' . (false !== $prev_root ? '=' . $prev_root : ''));
    if ($prev_cache_dir) {
        putenv("COMPOSER_CACHE_DIR={$prev_cache_dir}");
    }
    if ($exit) {
        exit($exit);
    }
    // Mutate TestCase code
    if (version_compare($PHPUNIT_VERSION, '11.0', '<')) {
        $altered_code = file_get_contents($altered_file = './src/Framework/TestCase.php');
        if ($PHPUNIT_REMOVE_RETURN_TYPEHINT) {
            $altered_code = preg_replace('/^    ((?:protected|public)(?: static)? function \w+\(\)): void/m', '    $1', $altered_code);
        }
        file_put_contents($altered_file, $altered_code);
        // Mutate Assert code
        $altered_code = file_get_contents($altered_file = './src/Framework/Assert.php');
        $altered_code = preg_replace('/abstract class Assert[^\{]+\{/', '$0 ' . \PHP_EOL . "    use \\Symfony\\Bridge\\PhpUnit\\Legacy\\PolyfillAssertTrait;", $altered_code, 1);
        file_put_contents($altered_file, $altered_code);
        file_put_contents('phpunit', <<<'EOPHP'
        <?php
        
        define('PHPUNIT_COMPOSER_INSTALL', __DIR__.'/vendor/autoload.php');
        require PHPUNIT_COMPOSER_INSTALL;
        
        if (!class_exists(\SymfonyExcludeListPhpunit::class, false)) {
            class SymfonyExcludeListPhpunit {}
        }
        if (method_exists(\PHPUnit\Util\ExcludeList::class, 'addDirectory')) {
            (new PHPUnit\Util\Excludelist())->getExcludedDirectories();
            PHPUnit\Util\ExcludeList::addDirectory(\dirname((new \ReflectionClass(\SymfonyExcludeListPhpunit::class))->getFileName()));
            class_exists(\SymfonyExcludeListSimplePhpunit::class, false) && PHPUnit\Util\ExcludeList::addDirectory(\dirname((new \ReflectionClass(\SymfonyExcludeListSimplePhpunit::class))->getFileName()));
        } elseif (method_exists(\PHPUnit\Util\Blacklist::class, 'addDirectory')) {
            (new PHPUnit\Util\BlackList())->getBlacklistedDirectories();
            PHPUnit\Util\Blacklist::addDirectory(\dirname((new \ReflectionClass(\SymfonyExcludeListPhpunit::class))->getFileName()));
            class_exists(\SymfonyExcludeListSimplePhpunit::class, false) && PHPUnit\Util\Blacklist::addDirectory(\dirname((new \ReflectionClass(\SymfonyExcludeListSimplePhpunit::class))->getFileName()));
        } else {
            PHPUnit\Util\Blacklist::$blacklistedClassNames['SymfonyExcludeListPhpunit'] = 1;
            PHPUnit\Util\Blacklist::$blacklistedClassNames['SymfonyExcludeListSimplePhpunit'] = 1;
        }
        
        Symfony\Bridge\PhpUnit\TextUI\Command::main();
        
        EOPHP);
    }
    chdir('..');
    file_put_contents(".{$PHPUNIT_VERSION_DIR}.md5", $configuration_hash);
    chdir($old_pwd);
}
// Create a symlink with a predictable path pointing to the currently used version.
// This is useful for static analytics tools such as PHPStan having to load PHPUnit's classes
// and for other testing libraries such as Behat using PHPUnit's assertions.
chdir($PHPUNIT_DIR);
if ('\\' === \DIRECTORY_SEPARATOR) {
    passthru('rmdir /S /Q phpunit 2> NUL');
    passthru(sprintf('mklink /j phpunit %s > NUL 2>&1', escapeshellarg($PHPUNIT_VERSION_DIR)));
} else {
    if (file_exists('phpunit')) {
        @unlink('phpunit');
    }
    @symlink($PHPUNIT_VERSION_DIR, 'phpunit');
}
chdir($old_pwd);
if (filter_var(getenv('SYMFONY_PHPUNIT_DISABLE_RESULT_CACHE'), \FILTER_VALIDATE_BOOLEAN)) {
    $argv[] = '--do-not-cache-result';
    ++$argc;
}
$components = [];
$cmd = array_map(escapeshellarg(...), $argv);
$exit = 0;
if (isset($argv[1]) && 'symfony' === $argv[1] && !file_exists('symfony') && file_exists('src/Symfony')) {
    $argv[1] = 'src/Symfony';
}
if (isset($argv[1]) && is_dir($argv[1]) && !file_exists($argv[1] . '/phpunit.xml.dist')) {
    // Find Symfony components in plain php for Windows portability
    $finder = new Recursive_Directory_Iterator($argv[1], Filesystem_Iterator::KEY_AS_FILENAME | Filesystem_Iterator::UNIX_PATHS | \Filesystem_Iterator::SKIP_DOTS);
    $finder = new Recursive_Iterator_Iterator($finder);
    $finder->set_max_depth(getenv('SYMFONY_PHPUNIT_MAX_DEPTH') ?: 3);
    foreach ($finder as $file => $file_info) {
        if ('phpunit.xml.dist' === $file) {
            $components[] = dirname((string) $file_info->get_pathname());
        }
    }
    if ($components) {
        array_shift($cmd);
    }
}
$cmd[0] = sprintf('%s %s --colors=%s', $PHP, escapeshellarg("{$PHPUNIT_DIR}/{$PHPUNIT_VERSION_DIR}/phpunit"), '' === $get_env_var('NO_COLOR', '') ? 'always' : 'never');
$cmd = str_replace('%', '%%', implode(' ', $cmd)) . ' %1$s';
if ('\\' === \DIRECTORY_SEPARATOR) {
    $cmd = 'cmd /v:on /d /c "(' . $cmd . ')%2$s"';
} else {
    $cmd .= '%2$s';
}
if (version_compare($PHPUNIT_VERSION, '11.0', '>=')) {
    $GLOBALS['_composer_autoload_path'] = "{$PHPUNIT_DIR}/{$PHPUNIT_VERSION_DIR}/vendor/autoload.php";
}
if ($components) {
    $skipped_tests = $_SERVER['SYMFONY_PHPUNIT_SKIPPED_TESTS'] ?? false;
    $running_procs = [];
    foreach ($components as $component) {
        // Run phpunit tests in parallel
        if ($skipped_tests) {
            putenv("SYMFONY_PHPUNIT_SKIPPED_TESTS={$component}/{$skipped_tests}");
        }
        $c = escapeshellarg($component);
        if ($proc = proc_open(sprintf($cmd, $c, " > {$c}/phpunit.stdout 2> {$c}/phpunit.stderr"), [], $pipes)) {
            $running_procs[$component] = $proc;
        } else {
            $exit = 1;
            echo "\x1b[41mKO\x1b[0m {$component}\n\n";
        }
    }
    $last_output = null;
    $last_output_time = null;
    while ($running_procs) {
        usleep(300000);
        $terminated_procs = [];
        foreach ($running_procs as $component => $proc) {
            $proc_status = proc_get_status($proc);
            if (!$proc_status['running']) {
                $terminated_procs[$component] = $proc_status['exitcode'];
                unset($running_procs[$component]);
                proc_close($proc);
            }
        }
        if (!$terminated_procs && 1 === count($running_procs)) {
            $component = key($running_procs);
            $output = file_get_contents("{$component}/phpunit.stdout");
            $output .= file_get_contents("{$component}/phpunit.stderr");
            if ($last_output !== $output) {
                $last_output = $output;
                $last_output_time = microtime(true);
            } elseif (microtime(true) - $last_output_time > 60) {
                echo "\x1b[41mTimeout\x1b[0m {$component}\n\n";
                if ('\\' === \DIRECTORY_SEPARATOR) {
                    exec(sprintf('taskkill /F /T /PID %d 2>&1', $proc_status['pid']), $output, $exit_code);
                } else {
                    proc_terminate(current($running_procs));
                }
            }
        }
        foreach ($terminated_procs as $component => $proc_status) {
            foreach (['out', 'err'] as $file) {
                $file = "{$component}/phpunit.std{$file}";
                readfile($file);
                unlink($file);
            }
            // Fail on any individual component failures but ignore some error codes on Windows when APCu is enabled:
            // STATUS_STACK_BUFFER_OVERRUN (-1073740791/0xC0000409)
            // STATUS_ACCESS_VIOLATION (-1073741819/0xC0000005)
            // STATUS_HEAP_CORRUPTION (-1073740940/0xC0000374)
            if ($proc_status && ('\\' !== \DIRECTORY_SEPARATOR || !extension_loaded('apcu') || !filter_var(ini_get('apc.enable_cli'), \FILTER_VALIDATE_BOOLEAN) || !in_array($proc_status, [-1073740791, -1073741819, -1073740940]))) {
                $exit = $proc_status;
                echo "\x1b[41mKO\x1b[0m {$component}\n\n";
            } else {
                echo "\x1b[32mOK\x1b[0m {$component}\n\n";
            }
        }
    }
} elseif (!isset($argv[1]) || 'install' !== $argv[1] || file_exists('install')) {
    if (!class_exists(Symfony_Exclude_List_Simple_Phpunit::class, false)) {
        class Symfony_Exclude_List_Simple_Phpunit
        {
        }
    }
    array_splice($argv, 1, 0, ['--colors=' . ('' === $get_env_var('NO_COLOR', '') ? 'always' : 'never')]);
    $_SERVER['argv'] = $argv;
    $_SERVER['argc'] = ++$argc;
    include "{$PHPUNIT_DIR}/{$PHPUNIT_VERSION_DIR}/phpunit";
}
exit($exit);