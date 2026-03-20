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
namespace Symfony\Component\Config;

use Symfony\Component\Config\Resource\Resource_Interface;
use Symfony\Component\Filesystem\Exception\Io_Exception;
use Symfony\Component\Filesystem\Filesystem;
/**
 * ResourceCheckerConfigCache uses instances of ResourceCheckerInterface
 * to check whether cached data is still fresh.
 *
 * @author Matthias Pigulla <mp@webfactory.de>
 */
class Resource_Checker_Config_Cache implements Config_Cache_Interface
{
    private readonly string $meta_file;
    /**
     * @param string                                    $file             The absolute cache path
     * @param iterable<mixed, ResourceCheckerInterface> $resourceCheckers The ResourceCheckers to use for the freshness check
     * @param string|null                               $metaFile         The absolute path to the meta file, defaults to $file.meta if null
     */
    public function __construct(private readonly string $file, private iterable $resource_checkers = [], ?string $meta_file = null)
    {
        $this->meta_file = $meta_file ?? $file . '.meta';
    }
    public function get_path(): string
    {
        return $this->file;
    }
    /**
     * Checks if the cache is still fresh.
     *
     * This implementation will make a decision solely based on the ResourceCheckers
     * passed in the constructor.
     *
     * The first ResourceChecker that supports a given resource is considered authoritative.
     * Resources with no matching ResourceChecker will silently be ignored and considered fresh.
     */
    public function is_fresh(): bool
    {
        if (!is_file($this->file)) {
            return false;
        }
        if ($this->resource_checkers instanceof \Traversable && !$this->resource_checkers instanceof \Countable) {
            $this->resource_checkers = iterator_to_array($this->resource_checkers);
        }
        if (!\count($this->resource_checkers)) {
            return true;
            // shortcut - if we don't have any checkers we don't need to bother with the meta file at all
        }
        $metadata = $this->meta_file;
        if (!is_file($metadata)) {
            return false;
        }
        $meta = $this->safely_unserialize($metadata);
        if (false === $meta) {
            return false;
        }
        $time = filemtime($this->file);
        foreach ($meta as $resource) {
            foreach ($this->resource_checkers as $checker) {
                if (!$checker->supports($resource)) {
                    continue;
                    // next checker
                }
                if ($checker->is_fresh($resource, $time)) {
                    break;
                    // no need to further check this resource
                }
                return false;
                // cache is stale
            }
            // no suitable checker found, ignore this resource
        }
        return true;
    }
    /**
     * Writes cache.
     *
     * @param string              $content  The content to write in the cache
     * @param ResourceInterface[] $metadata An array of metadata
     *
     * @throws \RuntimeException When cache file can't be written
     */
    public function write(string $content, ?array $metadata = null): void
    {
        $mode = 0666;
        $umask = umask();
        $filesystem = new Filesystem();
        $filesystem->dump_file($this->file, $content);
        try {
            $filesystem->chmod($this->file, $mode, $umask);
        } catch (Io_Exception) {
            // discard chmod failure (some filesystem may not support it)
        }
        if (null !== $metadata) {
            $filesystem->dump_file($this->meta_file, $ser = serialize($metadata));
            try {
                $filesystem->chmod($this->meta_file, $mode, $umask);
            } catch (Io_Exception) {
                // discard chmod failure (some filesystem may not support it)
            }
            $ser = preg_replace_callback('/;O:(\d+):"/', static fn($m): string => ';O:' . (9 + $m[1]) . ':"Tracking\\', $ser);
            $ser = preg_replace_callback('/s:(\d+):"(\0[^\0]++\0)/', static fn($m): string => 's:' . ($m[1] - \strlen((string) $m[2])) . ':"', (string) $ser);
            $ser = unserialize($ser, ['allowed_classes' => false]);
            $ser = @json_encode($ser, \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE) ?: [];
            $ser = str_replace('"__PHP_Incomplete_Class_Name":"Tracking\\\\', '"@type":"', $ser);
            $ser = \sprintf('{"resources":%s}', $ser);
            $filesystem->dump_file($this->meta_file . '.json', $ser);
            try {
                $filesystem->chmod($this->meta_file . '.json', $mode, $umask);
            } catch (Io_Exception) {
                // discard chmod failure (some filesystem may not support it)
            }
        }
        if (\function_exists('opcache_invalidate') && filter_var(\ini_get('opcache.enable'), \FILTER_VALIDATE_BOOL)) {
            @opcache_invalidate($this->file, true);
        }
    }
    private function safely_unserialize(string $file): mixed
    {
        $meta = false;
        $content = (new Filesystem())->read_file($file);
        $signaling_exception = new \UnexpectedValueException();
        $prev_unserialize_handler = ini_set('unserialize_callback_func', self::class . '::handleUnserializeCallback');
        $prev_error_handler = set_error_handler(static function ($type, $msg, $file, $line, $context = []) use (&$prev_error_handler, $signaling_exception) {
            if (__FILE__ === $file && !\in_array($type, [\E_DEPRECATED, \E_USER_DEPRECATED], true)) {
                throw $signaling_exception;
            }
            return $prev_error_handler ? $prev_error_handler($type, $msg, $file, $line, $context) : false;
        });
        try {
            $meta = unserialize($content);
        } catch (\Throwable $e) {
            if ($e !== $signaling_exception) {
                throw $e;
            }
        } finally {
            restore_error_handler();
            ini_set('unserialize_callback_func', $prev_unserialize_handler);
        }
        return $meta;
    }
    /**
     * @internal
     */
    public static function handle_unserialize_callback(string $class): void
    {
        trigger_error('Class not found: ' . $class);
    }
}