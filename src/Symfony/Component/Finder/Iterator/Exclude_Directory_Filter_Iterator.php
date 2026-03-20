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

use Symfony\Component\Finder\Spl_File_Info;
/**
 * ExcludeDirectoryFilterIterator filters out directories.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @extends \FilterIterator<string, SplFileInfo>
 *
 * @implements \RecursiveIterator<string, SplFileInfo>
 */
class Exclude_Directory_Filter_Iterator extends \Filter_Iterator implements \Recursive_Iterator
{
    private readonly bool $is_recursive;
    /** @var array<string, true> */
    private array $excluded_dirs = [];
    private ?string $excluded_pattern = null;
    /** @var list<callable(SplFileInfo):bool> */
    private array $prune_filters = [];
    /**
     * @param \Iterator<string, SplFileInfo>          $iterator    The Iterator to filter
     * @param list<string|callable(SplFileInfo):bool> $directories An array of directories to exclude
     */
    public function __construct(private readonly \Iterator $iterator, array $directories)
    {
        $this->is_recursive = $this->iterator instanceof \Recursive_Iterator;
        $patterns = [];
        foreach ($directories as $directory) {
            if (!\is_string($directory)) {
                if (!\is_callable($directory)) {
                    throw new \InvalidArgumentException('Invalid PHP callback.');
                }
                $this->prune_filters[] = $directory;
                continue;
            }
            $directory = rtrim($directory, '/');
            if (!$this->is_recursive || str_contains($directory, '/')) {
                $patterns[] = preg_quote($directory, '#');
            } else {
                $this->excluded_dirs[$directory] = true;
            }
        }
        if ($patterns) {
            $this->excluded_pattern = '#(?:^|/)(?:' . implode('|', $patterns) . ')(?:/|$)#';
        }
        parent::__construct($this->iterator);
    }
    /**
     * Filters the iterator values.
     */
    public function accept(): bool
    {
        if ($this->is_recursive && isset($this->excluded_dirs[$this->current()->get_filename()]) && $this->current()->is_dir()) {
            return false;
        }
        if ($this->excluded_pattern) {
            $path = $this->current()->is_dir() ? $this->current()->get_relative_pathname() : $this->current()->get_relative_path();
            $path = str_replace('\\', '/', $path);
            return !preg_match($this->excluded_pattern, $path);
        }
        if ($this->prune_filters && $this->has_children()) {
            foreach ($this->prune_filters as $prune_filter) {
                if (!$prune_filter($this->current())) {
                    return false;
                }
            }
        }
        return true;
    }
    public function has_children(): bool
    {
        return $this->is_recursive && $this->iterator->has_children();
    }
    public function get_children(): self
    {
        $children = new self($this->iterator->get_children(), []);
        $children->excluded_dirs = $this->excluded_dirs;
        $children->excluded_pattern = $this->excluded_pattern;
        return $children;
    }
}