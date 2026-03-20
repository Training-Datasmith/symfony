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
namespace Symfony\Component\Cache\Adapter;

use Symfony\Component\Cache\Exception\Cache_Exception;
use Symfony\Component\Cache\Exception\InvalidArgumentException;
use Symfony\Component\Cache\Pruneable_Interface;
use Symfony\Component\Cache\Traits\Cached_Value_Interface;
use Symfony\Component\Cache\Traits\Filesystem_Common_Trait;
use Symfony\Component\Var_Exporter\Var_Exporter;
/**
 * @author Piotr Stankowski <git@trakos.pl>
 * @author Nicolas Grekas <p@tchwork.com>
 * @author Rob Frawley 2nd <rmf@src.run>
 */
class Php_Files_Adapter extends Abstract_Adapter implements Pruneable_Interface
{
    use Filesystem_Common_Trait {
        doClear as private doCommonClear;
        doDelete as private doCommonDelete;
    }
    private \Closure $include_handler;
    private array $values = [];
    private array $files = [];
    private static int $start_time;
    private static array $values_cache = [];
    /**
     * @param bool $appendOnly Set to `true` to gain extra performance when the items stored in this pool never expire.
     *                         Doing so is encouraged because it fits perfectly OPcache's memory model.
     *
     * @throws CacheException if OPcache is not enabled
     */
    public function __construct(string $namespace = '', int $default_lifetime = 0, ?string $directory = null, private bool $append_only = false)
    {
        self::$start_time ??= $_SERVER['REQUEST_TIME'] ?? time();
        parent::__construct('', $default_lifetime);
        $this->init($namespace, $directory);
        $this->include_handler = static function ($type, $msg, $file, $line): void {
            throw new \ErrorException($msg, 0, $type, $file, $line);
        };
    }
    public static function is_supported(): bool
    {
        self::$start_time ??= $_SERVER['REQUEST_TIME'] ?? time();
        return \function_exists('opcache_invalidate') && filter_var(\ini_get('opcache.enable'), \FILTER_VALIDATE_BOOL) && (!\in_array(\PHP_SAPI, ['cli', 'phpdbg', 'embed'], true) || filter_var(\ini_get('opcache.enable_cli'), \FILTER_VALIDATE_BOOL));
    }
    public function prune(): bool
    {
        $time = time();
        $pruned = true;
        $get_expiry = true;
        set_error_handler($this->include_handler);
        try {
            foreach ($this->scan_hash_dir($this->directory) as $file) {
                try {
                    if (\is_array($expires_at = include $file)) {
                        $expires_at = $expires_at[0];
                    }
                } catch (\ErrorException) {
                    $expires_at = $time;
                }
                if ($time >= $expires_at) {
                    $pruned = ($this->do_unlink($file) || !file_exists($file)) && $pruned;
                }
            }
        } finally {
            restore_error_handler();
        }
        return $pruned;
    }
    protected function do_fetch(array $ids): iterable
    {
        if ($this->append_only) {
            $now = 0;
            $missing_ids = [];
        } else {
            $now = time();
            $missing_ids = $ids;
            $ids = [];
        }
        $values = [];
        while (true) {
            $get_expiry = false;
            foreach ($ids as $id) {
                if (null === $value = $this->values[$id] ?? null) {
                    $missing_ids[] = $id;
                } elseif ('N;' === $value) {
                    $values[$id] = null;
                } elseif (!\is_object($value)) {
                    $values[$id] = $value;
                } elseif ($value instanceof Cached_Value_Interface) {
                    $values[$id] = $value->get_value();
                } elseif (!$value instanceof Lazy_Value) {
                    $values[$id] = $value;
                } elseif (false === $values[$id] = include $value->file) {
                    unset($values[$id], $this->values[$id]);
                    $missing_ids[] = $id;
                }
                if (!$this->append_only) {
                    unset($this->values[$id]);
                }
            }
            if (!$missing_ids) {
                return $values;
            }
            set_error_handler($this->include_handler);
            try {
                $get_expiry = true;
                foreach ($missing_ids as $k => $id) {
                    try {
                        $file = $this->files[$id] ??= $this->get_file($id);
                        if (isset(self::$values_cache[$file])) {
                            [$expires_at, $this->values[$id]] = self::$values_cache[$file];
                        } elseif (\is_array($expires_at = include $file)) {
                            if ($this->append_only) {
                                self::$values_cache[$file] = $expires_at;
                            }
                            [$expires_at, $this->values[$id]] = $expires_at;
                        } elseif ($now < $expires_at) {
                            $this->values[$id] = new Lazy_Value($file);
                        }
                        if ($now >= $expires_at) {
                            unset($this->values[$id], $missing_ids[$k], self::$values_cache[$file]);
                        }
                    } catch (\ErrorException) {
                        unset($missing_ids[$k]);
                    }
                }
            } finally {
                restore_error_handler();
            }
            $ids = $missing_ids;
            $missing_ids = [];
        }
    }
    protected function do_have(string $id): bool
    {
        if ($this->append_only && isset($this->values[$id])) {
            return true;
        }
        set_error_handler($this->include_handler);
        try {
            $file = $this->files[$id] ??= $this->get_file($id);
            $get_expiry = true;
            if (isset(self::$values_cache[$file])) {
                [$expires_at, $value] = self::$values_cache[$file];
            } elseif (\is_array($expires_at = include $file)) {
                if ($this->append_only) {
                    self::$values_cache[$file] = $expires_at;
                }
                [$expires_at, $value] = $expires_at;
            } elseif ($this->append_only) {
                $value = new Lazy_Value($file);
            }
        } catch (\ErrorException) {
            return false;
        } finally {
            restore_error_handler();
        }
        if ($this->append_only) {
            $now = 0;
            $this->values[$id] = $value;
        } else {
            $now = time();
        }
        return $now < $expires_at;
    }
    protected function do_save(array $values, int $lifetime): array|bool
    {
        $ok = true;
        $expiry = $lifetime ? time() + $lifetime : 'PHP_INT_MAX';
        $allow_compile = self::is_supported();
        foreach ($values as $key => $value) {
            unset($this->values[$key]);
            $is_static_value = true;
            if (null === $value) {
                $value = "'N;'";
            } elseif (\is_object($value) || \is_array($value)) {
                try {
                    $value = Var_Exporter::export($value, $is_static_value);
                } catch (\Exception $e) {
                    throw new InvalidArgumentException(\sprintf('Cache key "%s" has non-serializable "%s" value.', $key, get_debug_type($value)), 0, $e);
                }
            } elseif (\is_string($value)) {
                // Wrap "N;" in a closure to not confuse it with an encoded `null`
                if ('N;' === $value) {
                    $is_static_value = false;
                }
                $value = var_export($value, true);
            } elseif (!\is_scalar($value)) {
                throw new InvalidArgumentException(\sprintf('Cache key "%s" has non-serializable "%s" value.', $key, get_debug_type($value)));
            } else {
                $value = var_export($value, true);
            }
            $encoded_key = rawurlencode((string) $key);
            if ($is_static_value) {
                $value = "return [{$expiry}, {$value}];";
            } elseif ($this->append_only) {
                $value = "return [{$expiry}, new class() implements \\" . Cached_Value_Interface::class . " { public function getValue(): mixed { return {$value}; } }];";
            } else {
                // We cannot use a closure here because of https://bugs.php.net/76982
                $value = str_replace('\Symfony\Component\VarExporter\Internal\\', '', $value);
                $value = "namespace Symfony\\Component\\VarExporter\\Internal;\n\nreturn \$getExpiry ? {$expiry} : {$value};";
            }
            $file = $this->files[$key] = $this->get_file($key, true);
            // Since OPcache only compiles files older than the script execution start, set the file's mtime in the past
            $ok = $this->write($file, "<?php //{$encoded_key}\n\n{$value}\n", self::$start_time - 10) && $ok;
            if ($allow_compile) {
                @opcache_invalidate($file, true);
                @opcache_compile_file($file);
            }
            unset(self::$values_cache[$file]);
        }
        if (!$ok && !is_writable($this->directory)) {
            throw new Cache_Exception(\sprintf('Cache directory is not writable (%s).', $this->directory));
        }
        return $ok;
    }
    protected function do_clear(string $namespace): bool
    {
        $this->values = [];
        return $this->do_common_clear($namespace);
    }
    protected function do_delete(array $ids): bool
    {
        foreach ($ids as $id) {
            unset($this->values[$id]);
        }
        return $this->do_common_delete($ids);
    }
    protected function do_unlink(string $file): bool
    {
        unset(self::$values_cache[$file]);
        if (self::is_supported()) {
            @opcache_invalidate($file, true);
        }
        return @unlink($file);
    }
    private function get_file_key(string $file): string
    {
        if (!$h = @fopen($file, 'r')) {
            return '';
        }
        $encoded_key = substr(fgets($h), 8);
        fclose($h);
        return rawurldecode(rtrim($encoded_key));
    }
}
/**
 * @internal
 */
class Lazy_Value
{
    public function __construct(public string $file)
    {
    }
}