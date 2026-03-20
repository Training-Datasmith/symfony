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
namespace Symfony\Component\Finder\Iterator;

use Symfony\Component\Finder\Gitignore;
/**
 * @extends \FilterIterator<string, \SplFileInfo>
 */
final class Vcs_Ignored_Filter_Iterator extends \Filter_Iterator
{
    private string $base_dir;
    /**
     * @var array<string, array{0: string, 1: string}|null>
     */
    private array $gitignore_files_cache = [];
    /**
     * @var array<string, bool>
     */
    private array $ignored_paths_cache = [];
    /**
     * @param \Iterator<string, \SplFileInfo> $iterator
     */
    public function __construct(\Iterator $iterator, string $base_dir)
    {
        $this->base_dir = $this->normalize_path($base_dir);
        foreach ([$this->base_dir, ...$this->parent_directories_upwards($this->base_dir)] as $directory) {
            if (@is_dir("{$directory}/.git")) {
                $this->base_dir = $directory;
                break;
            }
        }
        parent::__construct($iterator);
    }
    public function accept(): bool
    {
        $file = $this->current();
        $file_real_path = $this->normalize_path($file->get_real_path());
        return !$this->is_ignored($file_real_path);
    }
    private function is_ignored(string $file_real_path): bool
    {
        if (is_dir($file_real_path) && !str_ends_with($file_real_path, '/')) {
            $file_real_path .= '/';
        }
        if (isset($this->ignored_paths_cache[$file_real_path])) {
            return $this->ignored_paths_cache[$file_real_path];
        }
        $ignored = false;
        foreach ($this->parent_directories_downwards($file_real_path) as $parent_directory) {
            if ($this->is_ignored($parent_directory)) {
                // rules in ignored directories are ignored, no need to check further.
                break;
            }
            $file_relative_path = substr($file_real_path, \strlen($parent_directory) + 1);
            if (null === $regexps = $this->read_gitignore_file("{$parent_directory}/.gitignore")) {
                continue;
            }
            [$exclusion_regex, $inclusion_regex] = $regexps;
            if (preg_match($exclusion_regex, $file_relative_path)) {
                $ignored = true;
                continue;
            }
            if (preg_match($inclusion_regex, $file_relative_path)) {
                $ignored = false;
            }
        }
        return $this->ignored_paths_cache[$file_real_path] = $ignored;
    }
    /**
     * @return list<string>
     */
    private function parent_directories_upwards(string $from): array
    {
        $parent_directories = [];
        $parent_directory = $from;
        while (true) {
            $new_parent_directory = \dirname($parent_directory);
            // dirname('/') = '/'
            if ($new_parent_directory === $parent_directory) {
                break;
            }
            $parent_directories[] = $parent_directory = $new_parent_directory;
        }
        return $parent_directories;
    }
    private function parent_directories_up_to(string $from, string $up_to): array
    {
        return array_filter($this->parent_directories_upwards($from), static fn(string $directory): bool => str_starts_with($directory, $up_to));
    }
    /**
     * @return list<string>
     */
    private function parent_directories_downwards(string $file_real_path): array
    {
        return array_reverse($this->parent_directories_up_to($file_real_path, $this->base_dir));
    }
    /**
     * @return array{0: string, 1: string}|null
     */
    private function read_gitignore_file(string $path): ?array
    {
        if (\array_key_exists($path, $this->gitignore_files_cache)) {
            return $this->gitignore_files_cache[$path];
        }
        if (!file_exists($path)) {
            return $this->gitignore_files_cache[$path] = null;
        }
        if (!is_file($path) || !is_readable($path)) {
            throw new \RuntimeException("The \"ignoreVCSIgnored\" option cannot be used by the Finder as the \"{$path}\" file is not readable.");
        }
        $gitignore_file_content = file_get_contents($path);
        return $this->gitignore_files_cache[$path] = [Gitignore::to_regex($gitignore_file_content), Gitignore::to_regex_matching_negated_patterns($gitignore_file_content)];
    }
    private function normalize_path(string $path): string
    {
        if ('\\' === \DIRECTORY_SEPARATOR) {
            return str_replace('\\', '/', $path);
        }
        return $path;
    }
}