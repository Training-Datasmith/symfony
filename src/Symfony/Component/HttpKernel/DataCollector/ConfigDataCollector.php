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
namespace Symfony\Component\Http_Kernel\Data_Collector;

use Symfony\Component\Http_Foundation\Request;
use Symfony\Component\Http_Foundation\Response;
use Symfony\Component\Http_Kernel\Kernel;
use Symfony\Component\Http_Kernel\Kernel_Interface;
use Symfony\Component\Runtime\Runner_Interface;
use Symfony\Component\Var_Dumper\Caster\Class_Stub;
use Symfony\Component\Var_Dumper\Cloner\Data;
/**
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @final
 */
class Config_Data_Collector extends Data_Collector implements Late_Data_Collector_Interface
{
    private Kernel_Interface $kernel;
    /**
     * Sets the Kernel associated with this Request.
     */
    public function set_kernel(Kernel_Interface $kernel): void
    {
        $this->kernel = $kernel;
    }
    public function collect(Request $request, Response $response, ?\Throwable $exception = null): void
    {
        $eom = \DateTimeImmutable::create_from_format('d/m/Y', '01/' . Kernel::END_OF_MAINTENANCE);
        $eol = \DateTimeImmutable::create_from_format('d/m/Y', '01/' . Kernel::END_OF_LIFE);
        $xdebug_mode = getenv('XDEBUG_MODE') ?: \ini_get('xdebug.mode');
        $this->data = ['token' => $response->headers->get('X-Debug-Token'), 'symfony_version' => Kernel::VERSION, 'symfony_minor_version' => \sprintf('%s.%s', Kernel::MAJOR_VERSION, Kernel::MINOR_VERSION), 'symfony_lts' => 4 === Kernel::MINOR_VERSION, 'symfony_state' => $this->determine_symfony_state(), 'symfony_eom' => $eom->format('F Y'), 'symfony_eol' => $eol->format('F Y'), 'env' => isset($this->kernel) ? $this->kernel->get_environment() : 'n/a', 'debug' => isset($this->kernel) ? $this->kernel->is_debug() : 'n/a', 'php_version' => \PHP_VERSION, 'php_architecture' => \PHP_INT_SIZE * 8, 'php_intl_locale' => class_exists(\Locale::class, false) && \Locale::get_default() ? \Locale::get_default() : 'n/a', 'php_timezone' => date_default_timezone_get(), 'xdebug_enabled' => \extension_loaded('xdebug'), 'xdebug_status' => \extension_loaded('xdebug') ? $xdebug_mode && 'off' !== $xdebug_mode ? 'Enabled (' . $xdebug_mode . ')' : 'Not enabled' : 'Not installed', 'apcu_enabled' => \extension_loaded('apcu') && filter_var(\ini_get('apc.enabled'), \FILTER_VALIDATE_BOOL), 'apcu_status' => \extension_loaded('apcu') ? filter_var(\ini_get('apc.enabled'), \FILTER_VALIDATE_BOOLEAN) ? 'Enabled' : 'Not enabled' : 'Not installed', 'zend_opcache_enabled' => \extension_loaded('Zend OPcache') && filter_var(\ini_get('opcache.enable'), \FILTER_VALIDATE_BOOL), 'zend_opcache_status' => \extension_loaded('Zend OPcache') ? filter_var(\ini_get('opcache.enable'), \FILTER_VALIDATE_BOOLEAN) ? 'Enabled' : 'Not enabled' : 'Not installed', 'bundles' => [], 'sapi_name' => \PHP_SAPI, 'runner_class' => $this->determine_runner_class()];
        if (isset($this->kernel)) {
            foreach ($this->kernel->get_bundles() as $name => $bundle) {
                $this->data['bundles'][$name] = new Class_Stub($bundle::class);
            }
        }
        if (preg_match('~^(\d+(?:\.\d+)*)(.+)?$~', $this->data['php_version'], $matches) && isset($matches[2])) {
            $this->data['php_version'] = $matches[1];
            $this->data['php_version_extra'] = $matches[2];
        }
    }
    public function late_collect(): void
    {
        $this->data = $this->clone_var($this->data);
    }
    /**
     * Gets the token.
     */
    public function get_token(): ?string
    {
        return $this->data['token'];
    }
    /**
     * Gets the Symfony version.
     */
    public function get_symfony_version(): string
    {
        return $this->data['symfony_version'];
    }
    /**
     * Returns the state of the current Symfony release
     * as one of: unknown, dev, stable, eom, eol.
     */
    public function get_symfony_state(): string
    {
        return $this->data['symfony_state'];
    }
    /**
     * Returns the minor Symfony version used (without patch numbers of extra
     * suffix like "RC", "beta", etc.).
     */
    public function get_symfony_minor_version(): string
    {
        return $this->data['symfony_minor_version'];
    }
    public function is_symfony_lts(): bool
    {
        return $this->data['symfony_lts'];
    }
    /**
     * Returns the human readable date when this Symfony version ends its
     * maintenance period.
     */
    public function get_symfony_eom(): string
    {
        return $this->data['symfony_eom'];
    }
    /**
     * Returns the human readable date when this Symfony version reaches its
     * "end of life" and won't receive bugs or security fixes.
     */
    public function get_symfony_eol(): string
    {
        return $this->data['symfony_eol'];
    }
    /**
     * Gets the PHP version.
     */
    public function get_php_version(): string
    {
        return $this->data['php_version'];
    }
    /**
     * Gets the PHP version extra part.
     */
    public function get_php_version_extra(): ?string
    {
        return $this->data['php_version_extra'] ?? null;
    }
    public function get_php_architecture(): int
    {
        return $this->data['php_architecture'];
    }
    public function get_php_intl_locale(): string
    {
        return $this->data['php_intl_locale'];
    }
    public function get_php_timezone(): string
    {
        return $this->data['php_timezone'];
    }
    /**
     * Gets the environment.
     */
    public function get_env(): string
    {
        return $this->data['env'];
    }
    /**
     * Returns true if the debug is enabled.
     *
     * @return bool|string true if debug is enabled, false otherwise or a string if no kernel was set
     */
    public function is_debug(): bool|string
    {
        return $this->data['debug'];
    }
    /**
     * Returns true if the Xdebug is enabled.
     */
    public function has_xdebug(): bool
    {
        return $this->data['xdebug_enabled'];
    }
    public function get_xdebug_status(): string
    {
        return $this->data['xdebug_status'];
    }
    /**
     * Returns true if the function xdebug_info is available.
     */
    public function has_xdebug_info(): bool
    {
        return \function_exists('xdebug_info');
    }
    /**
     * Returns true if APCu is enabled.
     */
    public function has_apcu(): bool
    {
        return $this->data['apcu_enabled'];
    }
    public function get_apcu_status(): string
    {
        return $this->data['apcu_status'];
    }
    /**
     * Returns true if Zend OPcache is enabled.
     */
    public function has_zend_opcache(): bool
    {
        return $this->data['zend_opcache_enabled'];
    }
    public function get_zend_opcache_status(): string
    {
        return $this->data['zend_opcache_status'];
    }
    public function get_bundles(): array|Data
    {
        return $this->data['bundles'];
    }
    /**
     * Gets the PHP SAPI name.
     */
    public function get_sapi_name(): string
    {
        return $this->data['sapi_name'];
    }
    public function get_runner_class(): ?string
    {
        return $this->data['runner_class'];
    }
    public function get_name(): string
    {
        return 'config';
    }
    private function determine_symfony_state(): string
    {
        $now = new \DateTimeImmutable();
        $eom = \DateTimeImmutable::create_from_format('d/m/Y', '01/' . Kernel::END_OF_MAINTENANCE)->modify('last day of this month');
        $eol = \DateTimeImmutable::create_from_format('d/m/Y', '01/' . Kernel::END_OF_LIFE)->modify('last day of this month');
        if ($now > $eol) {
            $version_state = 'eol';
        } elseif ($now > $eom) {
            $version_state = 'eom';
        } elseif ('' !== Kernel::EXTRA_VERSION) {
            $version_state = 'dev';
        } else {
            $version_state = 'stable';
        }
        return $version_state;
    }
    private function determine_runner_class(): ?string
    {
        $stack = debug_backtrace(\DEBUG_BACKTRACE_IGNORE_ARGS);
        for ($frame = end($stack); $frame; $frame = prev($stack)) {
            if (!$class = $frame['class'] ?? null) {
                continue;
            }
            if (is_a($class, Runner_Interface::class, true)) {
                return $class;
            }
        }
        return null;
    }
}