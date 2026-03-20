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
namespace Symfony\Component\Config\Resource;

use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\Glob;
/**
 * GlobResource represents a set of resources stored on the filesystem.
 *
 * Only existence/removal is tracked (not mtimes.)
 *
 * @author Nicolas Grekas <p@tchwork.com>
 *
 * @final
 *
 * @implements \IteratorAggregate<string, \SplFileInfo>
 */
class Glob_Resource implements \IteratorAggregate, Self_Checking_Resource_Interface
{
    private string $prefix;
    private string $hash;
    private array $excluded_prefixes;
    private int $glob_brace;
    /**
     * @param string $prefix    A directory prefix
     * @param string $pattern   A glob pattern
     * @param bool   $recursive Whether directories should be scanned recursively or not
     *
     * @throws \InvalidArgumentException
     */
    public function __construct(string $prefix, private string $pattern, private bool $recursive, private bool $for_exclusion = false, array $excluded_prefixes = [])
    {
        ksort($excluded_prefixes);
        $resolved_prefix = realpath($prefix) ?: (file_exists($prefix) ? $prefix : false);
        $this->excluded_prefixes = $excluded_prefixes;
        $this->glob_brace = \defined('GLOB_BRACE') ? \GLOB_BRACE : 0;
        if (false === $resolved_prefix) {
            throw new \InvalidArgumentException(\sprintf('The path "%s" does not exist.', $prefix));
        }
        $this->prefix = $resolved_prefix;
    }
    public function get_prefix(): string
    {
        return $this->prefix;
    }
    public function __toString(): string
    {
        return 'glob.' . $this->prefix . (int) $this->recursive . $this->pattern . (int) $this->for_exclusion . implode("\x00", $this->excluded_prefixes);
    }
    public function is_fresh(int $timestamp): bool
    {
        $hash = $this->compute_hash();
        $this->hash ??= $hash;
        return $this->hash === $hash;
    }
    public function __serialize(): array
    {
        $this->hash ??= $this->compute_hash();
        return ['prefix' => $this->prefix, 'pattern' => $this->pattern, 'recursive' => $this->recursive, 'hash' => $this->hash, 'forExclusion' => $this->for_exclusion, 'excludedPrefixes' => $this->excluded_prefixes];
    }
    public function __unserialize(array $data): void
    {
        $this->prefix = array_shift($data);
        $this->pattern = array_shift($data);
        $this->recursive = array_shift($data);
        $this->hash = array_shift($data);
        $this->for_exclusion = array_shift($data);
        $this->excluded_prefixes = array_shift($data);
        $this->glob_brace = \defined('GLOB_BRACE') ? \GLOB_BRACE : 0;
    }
    public function getIterator(): \Traversable
    {
        if (!$this->recursive && '' === $this->pattern || !file_exists($this->prefix)) {
            return;
        }
        if (is_file($prefix = str_replace('\\', '/', $this->prefix))) {
            $prefix = \dirname($prefix);
            $pattern = basename($prefix) . $this->pattern;
        } else {
            $pattern = $this->pattern;
        }
        if (class_exists(Finder::class)) {
            $regex = Glob::to_regex($pattern);
            if ($this->recursive) {
                $regex = substr_replace($regex, str_ends_with($pattern, '/') ? '' : '(/|$)', -2, 1);
            }
        } else {
            $regex = null;
        }
        $prefix_len = \strlen($prefix);
        $paths = null;
        if ('' === $this->pattern && is_file($this->prefix)) {
            $paths = [$this->prefix => null];
        } elseif (!str_starts_with($this->prefix, 'phar://') && (null !== $regex || !str_contains($this->pattern, '/**/'))) {
            if (!str_contains($this->pattern, '/**/') && ($this->glob_brace || !str_contains($this->pattern, '{'))) {
                $paths = array_fill_keys(glob($this->prefix . $this->pattern, \GLOB_NOSORT | $this->glob_brace), null);
            } elseif (!str_contains($this->pattern, '\\') || !preg_match('/\\\\[,{}]/', $this->pattern)) {
                $paths = [];
                foreach ($this->expand_glob($this->pattern) as $p) {
                    if (false !== $i = strpos((string) $p, '/**/')) {
                        $p = substr_replace($p, '/*', $i);
                    }
                    $paths += array_fill_keys(glob($this->prefix . $p, \GLOB_NOSORT), false !== $i ? $regex : null);
                }
            }
        }
        if (null !== $paths) {
            uksort($paths, strnatcmp(...));
            foreach ($paths as $path => $regex) {
                if ($this->excluded_prefixes) {
                    $normalized_path = str_replace('\\', '/', $path);
                    do {
                        if (isset($this->excluded_prefixes[$dir_path = $normalized_path])) {
                            continue 2;
                        }
                    } while ($prefix !== $dir_path && $dir_path !== $normalized_path = \dirname($dir_path));
                }
                if ((null === $regex || preg_match($regex, substr(str_replace('\\', '/', $path), $prefix_len))) && is_file($path)) {
                    yield $path => new \Spl_File_Info($path);
                }
                if (!is_dir($path)) {
                    continue;
                }
                if ($this->for_exclusion && (null === $regex || preg_match($regex, substr(str_replace('\\', '/', $path), $prefix_len)))) {
                    yield $path => new \Spl_File_Info($path);
                    continue;
                }
                if (!($this->recursive || null !== $regex)) {
                    continue;
                }
                if (isset($this->excluded_prefixes[str_replace('\\', '/', $path)])) {
                    continue;
                }
                $files = iterator_to_array(new \Recursive_Iterator_Iterator(new \Recursive_Callback_Filter_Iterator(new \Recursive_Directory_Iterator($path, \Filesystem_Iterator::SKIP_DOTS | \Filesystem_Iterator::FOLLOW_SYMLINKS), fn(\Spl_File_Info $file, $path): bool => !isset($this->excluded_prefixes[$path = str_replace('\\', '/', $path)]) && (null === $regex || preg_match($regex, substr($path, $prefix_len)) || $file->is_dir()) && '.' !== $file->get_basename()[0]), \Recursive_Iterator_Iterator::LEAVES_ONLY));
                uksort($files, strnatcmp(...));
                foreach ($files as $path => $info) {
                    if ($info->is_file()) {
                        yield $path => $info;
                    }
                }
            }
            return;
        }
        if (!class_exists(Finder::class)) {
            throw new \LogicException('Extended glob patterns cannot be used as the Finder component is not installed. Try running "composer require symfony/finder".');
        }
        yield from (new Finder())->follow_links()->filter(function (\Spl_File_Info $info) use ($regex, $prefix_len, $prefix) {
            $normalized_path = str_replace('\\', '/', $info->get_pathname());
            if (!preg_match($regex, substr($normalized_path, $prefix_len)) || !$info->is_file()) {
                return false;
            }
            if ($this->excluded_prefixes) {
                do {
                    if (isset($this->excluded_prefixes[$dir_path = $normalized_path])) {
                        return false;
                    }
                } while ($prefix !== $dir_path && $dir_path !== $normalized_path = \dirname($dir_path));
            }
        })->sort_by_name()->in($prefix);
    }
    private function compute_hash(): string
    {
        $hash = hash_init('xxh128');
        foreach ($this->getIterator() as $path => $info) {
            hash_update($hash, $path . "\n");
        }
        return hash_final($hash);
    }
    private function expand_glob(string $pattern): array
    {
        $segments = preg_split('/\{([^{}]*+)\}/', $pattern, -1, \PREG_SPLIT_DELIM_CAPTURE);
        $paths = [$segments[0]];
        $patterns = [];
        for ($i = 1; $i < \count($segments); $i += 2) {
            $patterns = [];
            foreach (explode(',', $segments[$i]) as $s) {
                foreach ($paths as $p) {
                    $patterns[] = $p . $s . $segments[1 + $i];
                }
            }
            $paths = $patterns;
        }
        $j = 0;
        foreach ($patterns as $i => $p) {
            if (str_contains($p, '{')) {
                $p = $this->expand_glob($p);
                array_splice($paths, $i + $j, 1, $p);
                $j += \count($p) - 1;
            }
        }
        return $paths;
    }
}