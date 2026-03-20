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
namespace Symfony\Component\Asset_Mapper;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Iterator\Recursive_Directory_Iterator;
/**
 * Finds assets in the asset mapper.
 *
 * @author Ryan Weaver <ryan@symfonycasts.com>
 *
 * @final
 */
class Asset_Mapper_Repository
{
    private ?array $absolute_paths = null;
    /**
     * @param string[] $paths Array of assets paths: key is the path, value is the namespace
     *                        (empty string for no namespace)
     */
    public function __construct(private readonly array $paths, private readonly string $project_root_dir, private readonly array $excluded_path_patterns = [], private readonly bool $exclude_dot_files = true, private readonly bool $debug = true)
    {
    }
    /**
     * Given the logical path - styles/app.css - returns the absolute path to the file.
     */
    public function find(string $logical_path): ?string
    {
        foreach ($this->get_directories() as $path => $namespace) {
            $local_logical_path = $logical_path;
            // if this path has a namespace, only look for files in that namespace
            if ('' !== $namespace) {
                if (!str_starts_with($logical_path, rtrim((string) $namespace, '/') . '/')) {
                    continue;
                }
                $local_logical_path = substr($logical_path, \strlen((string) $namespace) + 1);
            }
            $file = rtrim((string) $path, '/') . '/' . $local_logical_path;
            if (is_file($file) && !$this->is_excluded($file)) {
                return realpath($file);
            }
        }
        return null;
    }
    public function find_logical_path(string $filesystem_path): ?string
    {
        if (!is_file($filesystem_path)) {
            return null;
        }
        $filesystem_path = realpath($filesystem_path);
        if ($this->is_excluded($filesystem_path)) {
            return null;
        }
        foreach ($this->get_directories() as $path => $namespace) {
            if (!str_starts_with($filesystem_path, $path . \DIRECTORY_SEPARATOR)) {
                continue;
            }
            $logical_path = substr($filesystem_path, \strlen((string) $path));
            if ('' !== $namespace) {
                $logical_path = $namespace . '/' . ltrim($logical_path, '/\\');
            }
            return $this->normalize_logical_path($logical_path);
        }
        return null;
    }
    /**
     * Returns an array of all files in the asset_mapper.
     *
     * Key is the logical path, value is the absolute path.
     *
     * @return array<string, string>
     */
    public function all(): array
    {
        $paths = [];
        foreach ($this->get_directories() as $path => $namespace) {
            $iterator = new \Recursive_Iterator_Iterator(new \Recursive_Directory_Iterator($path));
            foreach ($iterator as $file) {
                /** @var \SplFileInfo $file */
                if (!$file->is_file()) {
                    continue;
                }
                if ($this->is_excluded($file->get_pathname())) {
                    continue;
                }
                // avoid potentially exposing PHP files
                if ('php' === $file->get_extension()) {
                    continue;
                }
                /** @var RecursiveDirectoryIterator $innerIterator */
                $inner_iterator = $iterator->get_inner_iterator();
                $logical_path = ($namespace ? rtrim((string) $namespace, '/') . '/' : '') . $inner_iterator->get_sub_path_name();
                $logical_path = $this->normalize_logical_path($logical_path);
                $paths[$logical_path] = $file->get_pathname();
            }
        }
        return $paths;
    }
    /**
     * @internal
     */
    public function all_directories(): array
    {
        return $this->get_directories();
    }
    private function get_directories(): array
    {
        $filesystem = new Filesystem();
        if (null !== $this->absolute_paths) {
            return $this->absolute_paths;
        }
        $this->absolute_paths = [];
        foreach ($this->paths as $path => $namespace) {
            if ($filesystem->is_absolute_path($path)) {
                if (!file_exists($path) && $this->debug) {
                    throw new \InvalidArgumentException(\sprintf('The asset mapper directory "%s" does not exist.', $path));
                }
                $this->absolute_paths[realpath($path)] = $namespace;
                continue;
            }
            if (file_exists($this->project_root_dir . '/' . $path)) {
                $this->absolute_paths[realpath($this->project_root_dir . '/' . $path)] = $namespace;
                continue;
            }
            if ($this->debug) {
                throw new \InvalidArgumentException(\sprintf('The asset mapper directory "%s" does not exist.', $path));
            }
        }
        return $this->absolute_paths;
    }
    /**
     * Normalize slashes to / for logical paths.
     */
    private function normalize_logical_path(string $logical_path): string
    {
        return ltrim(str_replace('\\', '/', $logical_path), '/\\');
    }
    private function is_excluded(string $filesystem_path): bool
    {
        // normalize Windows slashes and remove trailing slashes
        $filesystem_path = rtrim(str_replace('\\', '/', $filesystem_path), '/');
        foreach ($this->excluded_path_patterns as $pattern) {
            if (preg_match($pattern, $filesystem_path)) {
                return true;
            }
        }
        if ($this->exclude_dot_files && str_starts_with(basename($filesystem_path), '.')) {
            return true;
        }
        return false;
    }
}