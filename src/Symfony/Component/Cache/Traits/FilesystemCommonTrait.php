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
namespace Symfony\Component\Cache\Traits;

use Symfony\Component\Cache\Exception\InvalidArgumentException;
/**
 * @author Nicolas Grekas <p@tchwork.com>
 *
 * @internal
 */
trait Filesystem_Common_Trait
{
    private string $directory;
    private string $tmp_suffix;
    private function init(string $namespace, ?string $directory): void
    {
        if (!isset($directory[0])) {
            $directory = sys_get_temp_dir() . \DIRECTORY_SEPARATOR . 'symfony-cache';
        } else {
            $directory = realpath($directory) ?: $directory;
        }
        if (isset($namespace[0])) {
            if (preg_match('#[^-+_.A-Za-z0-9]#', $namespace, $match)) {
                throw new InvalidArgumentException(\sprintf('Namespace contains "%s" but only characters in [-+_.A-Za-z0-9] are allowed.', $match[0]));
            }
            $directory .= \DIRECTORY_SEPARATOR . $namespace;
        } else {
            $directory .= \DIRECTORY_SEPARATOR . '@';
        }
        if (!is_dir($directory)) {
            @mkdir($directory, 0777, true);
        }
        $directory .= \DIRECTORY_SEPARATOR;
        // On Windows the whole path is limited to 258 chars
        if ('\\' === \DIRECTORY_SEPARATOR && \strlen($directory) > 234) {
            throw new InvalidArgumentException(\sprintf('Cache directory too long (%s).', $directory));
        }
        $this->directory = $directory;
    }
    protected function do_clear(string $namespace): bool
    {
        $ok = true;
        foreach ($this->scan_hash_dir($this->directory) as $file) {
            if ('' !== $namespace && !str_starts_with($this->get_file_key($file), $namespace)) {
                continue;
            }
            $ok = ($this->do_unlink($file) || !file_exists($file)) && $ok;
        }
        return $ok;
    }
    protected function do_delete(array $ids): bool
    {
        $ok = true;
        foreach ($ids as $id) {
            $file = $this->get_file($id);
            $ok = (!is_file($file) || $this->do_unlink($file) || !file_exists($file)) && $ok;
        }
        return $ok;
    }
    protected function do_unlink(string $file): bool
    {
        return @unlink($file);
    }
    private function write(string $file, string $data, ?int $expires_at = null): bool
    {
        $unlink = false;
        set_error_handler(static fn($type, $message, $file, $line) => throw new \ErrorException($message, 0, $type, $file, $line));
        try {
            $tmp = $this->directory . $this->tmp_suffix ??= str_replace('/', '-', base64_encode(random_bytes(6)));
            try {
                $h = fopen($tmp, 'x');
            } catch (\ErrorException $e) {
                if (!str_contains($e->get_message(), 'File exists')) {
                    throw $e;
                }
                $tmp = $this->directory . $this->tmp_suffix = str_replace('/', '-', base64_encode(random_bytes(6)));
                $h = fopen($tmp, 'x');
            }
            fwrite($h, $data);
            fclose($h);
            $unlink = true;
            if (null !== $expires_at) {
                touch($tmp, $expires_at ?: time() + 31556952);
                // 1 year in seconds
            }
            if ('\\' === \DIRECTORY_SEPARATOR) {
                $success = copy($tmp, $file);
            } else {
                $success = rename($tmp, $file);
                $unlink = !$success;
            }
            return $success;
        } finally {
            restore_error_handler();
            if ($unlink) {
                @unlink($tmp);
            }
        }
    }
    private function get_file(string $id, bool $mkdir = false, ?string $directory = null): string
    {
        // Use xxh128 to favor speed over security, which is not an issue here
        $hash = str_replace('/', '-', base64_encode(hash('xxh128', static::class . $id, true)));
        $dir = ($directory ?? $this->directory) . strtoupper($hash[0] . \DIRECTORY_SEPARATOR . $hash[1] . \DIRECTORY_SEPARATOR);
        if ($mkdir && !is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }
        return $dir . substr($hash, 2, 20);
    }
    private function get_file_key(string $file): string
    {
        return '';
    }
    private function scan_hash_dir(string $directory): \Generator
    {
        if (!is_dir($directory)) {
            return;
        }
        $chars = '+-ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        for ($i = 0; $i < 38; ++$i) {
            if (!is_dir($directory . $chars[$i])) {
                continue;
            }
            for ($j = 0; $j < 38; ++$j) {
                if (!is_dir($dir = $directory . $chars[$i] . \DIRECTORY_SEPARATOR . $chars[$j])) {
                    continue;
                }
                foreach (@scandir($dir, \SCANDIR_SORT_NONE) ?: [] as $file) {
                    if ('.' !== $file && '..' !== $file) {
                        yield $dir . \DIRECTORY_SEPARATOR . $file;
                    }
                }
            }
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
        if (method_exists(parent::class, '__destruct')) {
            parent::__destruct();
        }
        if (isset($this->tmp_suffix) && is_file($this->directory . $this->tmp_suffix)) {
            unlink($this->directory . $this->tmp_suffix);
        }
    }
}