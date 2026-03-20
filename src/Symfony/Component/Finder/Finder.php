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
namespace Symfony\Component\Finder;

use Symfony\Component\Finder\Comparator\Date_Comparator;
use Symfony\Component\Finder\Comparator\Number_Comparator;
use Symfony\Component\Finder\Exception\Directory_Not_Found_Exception;
use Symfony\Component\Finder\Iterator\Custom_Filter_Iterator;
use Symfony\Component\Finder\Iterator\Date_Range_Filter_Iterator;
use Symfony\Component\Finder\Iterator\Depth_Range_Filter_Iterator;
use Symfony\Component\Finder\Iterator\Exclude_Directory_Filter_Iterator;
use Symfony\Component\Finder\Iterator\Filecontent_Filter_Iterator;
use Symfony\Component\Finder\Iterator\Filename_Filter_Iterator;
use Symfony\Component\Finder\Iterator\Lazy_Iterator;
use Symfony\Component\Finder\Iterator\Size_Range_Filter_Iterator;
use Symfony\Component\Finder\Iterator\Sortable_Iterator;
/**
 * Finder allows to build rules to find files and directories.
 *
 * It is a thin wrapper around several specialized iterator classes.
 *
 * All rules may be invoked several times.
 *
 * All methods return the current Finder object to allow chaining:
 *
 *     $finder = Finder::create()->files()->name('*.php')->in(__DIR__);
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @implements \IteratorAggregate<non-empty-string, SplFileInfo>
 */
class Finder implements \IteratorAggregate, \Countable
{
    public const IGNORE_VCS_FILES = 1;
    public const IGNORE_DOT_FILES = 2;
    public const IGNORE_VCS_IGNORED_FILES = 4;
    private int $mode = 0;
    private array $names = [];
    private array $not_names = [];
    private array $exclude = [];
    private array $filters = [];
    private array $prune_filters = [];
    private array $depths = [];
    private array $sizes = [];
    private bool $follow_links = false;
    private bool $unix_paths = false;
    private bool $reverse_sorting = false;
    private \Closure|int|false $sort = false;
    private int $ignore = 0;
    /** @var list<string> */
    private array $dirs = [];
    private array $dates = [];
    /** @var list<iterable<SplFileInfo|\SplFileInfo|string>> */
    private array $iterators = [];
    private array $contains = [];
    private array $not_contains = [];
    private array $paths = [];
    private array $not_paths = [];
    private bool $ignore_unreadable_dirs = false;
    private static array $vcs_patterns = ['.svn', '_svn', 'CVS', '_darcs', '.arch-params', '.monotone', '.bzr', '.git', '.hg'];
    public function __construct()
    {
        $this->ignore = static::IGNORE_VCS_FILES | static::IGNORE_DOT_FILES;
    }
    /**
     * Creates a new Finder.
     */
    public static function create(): static
    {
        return new static();
    }
    /**
     * Restricts the matching to directories only.
     *
     * @return $this
     */
    public function directories(): static
    {
        $this->mode = Iterator\File_Type_Filter_Iterator::ONLY_DIRECTORIES;
        return $this;
    }
    /**
     * Restricts the matching to files only.
     *
     * @return $this
     */
    public function files(): static
    {
        $this->mode = Iterator\File_Type_Filter_Iterator::ONLY_FILES;
        return $this;
    }
    /**
     * Adds tests for the directory depth.
     *
     * Usage:
     *
     *     $finder->depth('> 1') // the Finder will start matching at level 1.
     *     $finder->depth('< 3') // the Finder will descend at most 3 levels of directories below the starting point.
     *     $finder->depth(['>= 1', '< 3'])
     *
     * @param string|int|string[]|int[] $levels The depth level expression or an array of depth levels
     *
     * @return $this
     *
     * @see DepthRangeFilterIterator
     * @see NumberComparator
     */
    public function depth(string|int|array $levels): static
    {
        foreach ((array) $levels as $level) {
            $this->depths[] = new Number_Comparator($level);
        }
        return $this;
    }
    /**
     * Adds tests for file dates (last modified).
     *
     * The date must be something that strtotime() is able to parse:
     *
     *     $finder->date('since yesterday');
     *     $finder->date('until 2 days ago');
     *     $finder->date('> now - 2 hours');
     *     $finder->date('>= 2005-10-15');
     *     $finder->date(['>= 2005-10-15', '<= 2006-05-27']);
     *
     * @param string|string[] $dates A date range string or an array of date ranges
     *
     * @return $this
     *
     * @see strtotime
     * @see DateRangeFilterIterator
     * @see DateComparator
     */
    public function date(string|array $dates): static
    {
        foreach ((array) $dates as $date) {
            $this->dates[] = new Date_Comparator($date);
        }
        return $this;
    }
    /**
     * Adds rules that files must match.
     *
     * You can use patterns (delimited with / sign), globs or simple strings.
     *
     *     $finder->name('/\.php$/')
     *     $finder->name('*.php') // same as above, without dot files
     *     $finder->name('test.php')
     *     $finder->name(['test.py', 'test.php'])
     *
     * @param string|string[] $patterns A pattern (a regexp, a glob, or a string) or an array of patterns
     *
     * @return $this
     *
     * @see FilenameFilterIterator
     */
    public function name(string|array $patterns): static
    {
        $this->names = array_merge($this->names, (array) $patterns);
        return $this;
    }
    /**
     * Adds rules that files must not match.
     *
     * @param string|string[] $patterns A pattern (a regexp, a glob, or a string) or an array of patterns
     *
     * @return $this
     *
     * @see FilenameFilterIterator
     */
    public function not_name(string|array $patterns): static
    {
        $this->not_names = array_merge($this->not_names, (array) $patterns);
        return $this;
    }
    /**
     * Adds tests that file contents must match.
     *
     * Strings or PCRE patterns can be used:
     *
     *     $finder->contains('Lorem ipsum')
     *     $finder->contains('/Lorem ipsum/i')
     *     $finder->contains(['dolor', '/ipsum/i'])
     *
     * @param string|string[] $patterns A pattern (string or regexp) or an array of patterns
     *
     * @return $this
     *
     * @see FilecontentFilterIterator
     */
    public function contains(string|array $patterns): static
    {
        $this->contains = array_merge($this->contains, (array) $patterns);
        return $this;
    }
    /**
     * Adds tests that file contents must not match.
     *
     * Strings or PCRE patterns can be used:
     *
     *     $finder->notContains('Lorem ipsum')
     *     $finder->notContains('/Lorem ipsum/i')
     *     $finder->notContains(['lorem', '/dolor/i'])
     *
     * @param string|string[] $patterns A pattern (string or regexp) or an array of patterns
     *
     * @return $this
     *
     * @see FilecontentFilterIterator
     */
    public function not_contains(string|array $patterns): static
    {
        $this->not_contains = array_merge($this->not_contains, (array) $patterns);
        return $this;
    }
    /**
     * Adds rules that filenames must match.
     *
     * You can use patterns (delimited with / sign) or simple strings.
     *
     *     $finder->path('some/special/dir')
     *     $finder->path('/some\/special\/dir/') // same as above
     *     $finder->path(['some dir', 'another/dir'])
     *
     * Use only / as dirname separator.
     *
     * @param string|string[] $patterns A pattern (a regexp or a string) or an array of patterns
     *
     * @return $this
     *
     * @see FilenameFilterIterator
     */
    public function path(string|array $patterns): static
    {
        $this->paths = array_merge($this->paths, (array) $patterns);
        return $this;
    }
    /**
     * Adds rules that filenames must not match.
     *
     * You can use patterns (delimited with / sign) or simple strings.
     *
     *     $finder->notPath('some/special/dir')
     *     $finder->notPath('/some\/special\/dir/') // same as above
     *     $finder->notPath(['some/file.txt', 'another/file.log'])
     *
     * Use only / as dirname separator.
     *
     * @param string|string[] $patterns A pattern (a regexp or a string) or an array of patterns
     *
     * @return $this
     *
     * @see FilenameFilterIterator
     */
    public function not_path(string|array $patterns): static
    {
        $this->not_paths = array_merge($this->not_paths, (array) $patterns);
        return $this;
    }
    /**
     * Adds tests for file sizes.
     *
     *     $finder->size('> 10K');
     *     $finder->size('<= 1Ki');
     *     $finder->size(4);
     *     $finder->size(['> 10K', '< 20K'])
     *
     * @param string|int|string[]|int[] $sizes A size range string or an integer or an array of size ranges
     *
     * @return $this
     *
     * @see SizeRangeFilterIterator
     * @see NumberComparator
     */
    public function size(string|int|array $sizes): static
    {
        foreach ((array) $sizes as $size) {
            $this->sizes[] = new Number_Comparator($size);
        }
        return $this;
    }
    /**
     * Excludes directories.
     *
     * Directories passed as argument must be relative to the ones defined with the `in()` method. For example:
     *
     *     $finder->in(__DIR__)->exclude('ruby');
     *
     * @param string|array $dirs A directory path or an array of directories
     *
     * @return $this
     *
     * @see ExcludeDirectoryFilterIterator
     */
    public function exclude(string|array $dirs): static
    {
        $this->exclude = array_merge($this->exclude, (array) $dirs);
        return $this;
    }
    /**
     * Excludes "hidden" directories and files (starting with a dot).
     *
     * This option is enabled by default.
     *
     * @return $this
     *
     * @see ExcludeDirectoryFilterIterator
     */
    public function ignore_dot_files(bool $ignore_dot_files): static
    {
        if ($ignore_dot_files) {
            $this->ignore |= static::IGNORE_DOT_FILES;
        } else {
            $this->ignore &= ~static::IGNORE_DOT_FILES;
        }
        return $this;
    }
    /**
     * Forces the finder to ignore version control directories.
     *
     * This option is enabled by default.
     *
     * @return $this
     *
     * @see ExcludeDirectoryFilterIterator
     */
    public function ignore_vcs(bool $ignore_vcs): static
    {
        if ($ignore_vcs) {
            $this->ignore |= static::IGNORE_VCS_FILES;
        } else {
            $this->ignore &= ~static::IGNORE_VCS_FILES;
        }
        return $this;
    }
    /**
     * Forces Finder to obey .gitignore and ignore files based on rules listed there.
     *
     * This option is disabled by default.
     *
     * @return $this
     */
    public function ignore_vcs_ignored(bool $ignore_vcs_ignored): static
    {
        if ($ignore_vcs_ignored) {
            $this->ignore |= static::IGNORE_VCS_IGNORED_FILES;
        } else {
            $this->ignore &= ~static::IGNORE_VCS_IGNORED_FILES;
        }
        return $this;
    }
    /**
     * Adds VCS patterns.
     *
     * @see ignoreVCS()
     *
     * @param string|string[] $pattern VCS patterns to ignore
     */
    public static function add_vcs_pattern(string|array $pattern): void
    {
        foreach ((array) $pattern as $p) {
            self::$vcs_patterns[] = $p;
        }
        self::$vcs_patterns = array_unique(self::$vcs_patterns);
    }
    /**
     * Sorts files and directories by an anonymous function.
     *
     * The anonymous function receives two \SplFileInfo instances to compare.
     *
     * This can be slow as all the matching files and directories must be retrieved for comparison.
     *
     * @return $this
     *
     * @see SortableIterator
     */
    public function sort(\Closure $closure): static
    {
        $this->sort = $closure;
        return $this;
    }
    /**
     * Sorts files and directories by extension.
     *
     * This can be slow as all the matching files and directories must be retrieved for comparison.
     *
     * @return $this
     *
     * @see SortableIterator
     */
    public function sort_by_extension(): static
    {
        $this->sort = Sortable_Iterator::SORT_BY_EXTENSION;
        return $this;
    }
    /**
     * Sorts files and directories by name.
     *
     * This can be slow as all the matching files and directories must be retrieved for comparison.
     *
     * @return $this
     *
     * @see SortableIterator
     */
    public function sort_by_name(bool $use_natural_sort = false): static
    {
        $this->sort = $use_natural_sort ? Sortable_Iterator::SORT_BY_NAME_NATURAL : Sortable_Iterator::SORT_BY_NAME;
        return $this;
    }
    /**
     * Sorts files and directories by name case insensitive.
     *
     * This can be slow as all the matching files and directories must be retrieved for comparison.
     *
     * @return $this
     *
     * @see SortableIterator
     */
    public function sort_by_case_insensitive_name(bool $use_natural_sort = false): static
    {
        $this->sort = $use_natural_sort ? Sortable_Iterator::SORT_BY_NAME_NATURAL_CASE_INSENSITIVE : Sortable_Iterator::SORT_BY_NAME_CASE_INSENSITIVE;
        return $this;
    }
    /**
     * Sorts files and directories by size.
     *
     * This can be slow as all the matching files and directories must be retrieved for comparison.
     *
     * @return $this
     *
     * @see SortableIterator
     */
    public function sort_by_size(): static
    {
        $this->sort = Sortable_Iterator::SORT_BY_SIZE;
        return $this;
    }
    /**
     * Sorts files and directories by type (directories before files), then by name.
     *
     * This can be slow as all the matching files and directories must be retrieved for comparison.
     *
     * @return $this
     *
     * @see SortableIterator
     */
    public function sort_by_type(): static
    {
        $this->sort = Sortable_Iterator::SORT_BY_TYPE;
        return $this;
    }
    /**
     * Sorts files and directories by the last accessed time.
     *
     * This is the time that the file was last accessed, read or written to.
     *
     * This can be slow as all the matching files and directories must be retrieved for comparison.
     *
     * @return $this
     *
     * @see SortableIterator
     */
    public function sort_by_accessed_time(): static
    {
        $this->sort = Sortable_Iterator::SORT_BY_ACCESSED_TIME;
        return $this;
    }
    /**
     * Reverses the sorting.
     *
     * @return $this
     */
    public function reverse_sorting(): static
    {
        $this->reverse_sorting = true;
        return $this;
    }
    /**
     * Sorts files and directories by the last inode changed time.
     *
     * This is the time that the inode information was last modified (permissions, owner, group or other metadata).
     *
     * On Windows, since inode is not available, changed time is actually the file creation time.
     *
     * This can be slow as all the matching files and directories must be retrieved for comparison.
     *
     * @return $this
     *
     * @see SortableIterator
     */
    public function sort_by_changed_time(): static
    {
        $this->sort = Sortable_Iterator::SORT_BY_CHANGED_TIME;
        return $this;
    }
    /**
     * Sorts files and directories by the last modified time.
     *
     * This is the last time the actual contents of the file were last modified.
     *
     * This can be slow as all the matching files and directories must be retrieved for comparison.
     *
     * @return $this
     *
     * @see SortableIterator
     */
    public function sort_by_modified_time(): static
    {
        $this->sort = Sortable_Iterator::SORT_BY_MODIFIED_TIME;
        return $this;
    }
    /**
     * Filters the iterator with an anonymous function.
     *
     * The anonymous function receives a \SplFileInfo and must return false
     * to remove files.
     *
     * @param \Closure(SplFileInfo): bool $closure
     * @param bool                        $prune   Whether to skip traversing directories further
     *
     * @return $this
     *
     * @see CustomFilterIterator
     */
    public function filter(\Closure $closure, bool $prune = false): static
    {
        $this->filters[] = $closure;
        if ($prune) {
            $this->prune_filters[] = $closure;
        }
        return $this;
    }
    /**
     * Forces the following of symlinks.
     *
     * @return $this
     */
    public function follow_links(): static
    {
        $this->follow_links = true;
        return $this;
    }
    /**
     * Force the use of UNIX paths when recursing directories.
     *
     * @return $this
     */
    public function use_unix_paths(): static
    {
        $this->unix_paths = true;
        return $this;
    }
    /**
     * Tells finder to ignore unreadable directories.
     *
     * By default, scanning unreadable directories content throws an AccessDeniedException.
     *
     * @return $this
     */
    public function ignore_unreadable_dirs(bool $ignore = true): static
    {
        $this->ignore_unreadable_dirs = $ignore;
        return $this;
    }
    /**
     * Searches files and directories which match defined rules.
     *
     * @param string|string[] $dirs A directory path or an array of directories
     *
     * @return $this
     *
     * @throws DirectoryNotFoundException if one of the directories does not exist
     */
    public function in(string|array $dirs): static
    {
        $resolved_dirs = [];
        foreach ((array) $dirs as $dir) {
            if (is_dir($dir)) {
                $resolved_dirs[] = [$this->normalize_dir($dir)];
            } elseif ($glob = glob($dir, (\defined('GLOB_BRACE') ? \GLOB_BRACE : 0) | \GLOB_ONLYDIR | \GLOB_NOSORT)) {
                sort($glob);
                $resolved_dirs[] = array_map($this->normalize_dir(...), $glob);
            } else {
                throw new Directory_Not_Found_Exception(\sprintf('The "%s" directory does not exist.', $dir));
            }
        }
        $this->dirs = array_merge($this->dirs, ...$resolved_dirs);
        return $this;
    }
    /**
     * Returns an Iterator for the current Finder configuration.
     *
     * This method implements the IteratorAggregate interface.
     *
     * @return \Iterator<non-empty-string, SplFileInfo>
     *
     * @throws \LogicException if the in() method has not been called
     */
    public function getIterator(): \Iterator
    {
        if (!$this->dirs && !$this->iterators) {
            throw new \LogicException('You must call one of in() or append() methods before iterating over a Finder.');
        }
        if (1 === \count($this->dirs) && !$this->iterators) {
            $iterator = $this->search_in_directory($this->dirs[0]);
        } else {
            $iterator = new \Append_Iterator();
            foreach ($this->dirs as $dir) {
                $iterator->append(new \Iterator_Iterator(new Lazy_Iterator(fn(): \Iterator => $this->search_in_directory($dir))));
            }
            foreach ($this->iterators as $it) {
                $iterator->append(new \Iterator_Iterator(new Lazy_Iterator(static function () use ($it) {
                    foreach ($it as $file) {
                        if (!$file instanceof \Spl_File_Info) {
                            $file = new \Spl_File_Info($file);
                        }
                        $key = $file->get_pathname();
                        if (!$file instanceof Spl_File_Info) {
                            $file = new Spl_File_Info($key, $file->get_path(), $key);
                        }
                        yield $key => $file;
                    }
                })));
            }
        }
        if ($this->sort || $this->reverse_sorting) {
            return (new Sortable_Iterator($iterator, $this->sort, $this->reverse_sorting))->getIterator();
        }
        return $iterator;
    }
    /**
     * Appends an existing set of files/directories to the finder.
     *
     * The set can be another Finder, an Iterator, an IteratorAggregate, or even a plain array.
     *
     * @param iterable<SplFileInfo|\SplFileInfo|string> $iterator
     *
     * @return $this
     */
    public function append(iterable $iterator): static
    {
        $this->iterators[] = $iterator;
        return $this;
    }
    /**
     * Check if any results were found.
     */
    public function has_results(): bool
    {
        foreach ($this->getIterator() as $_) {
            return true;
        }
        return false;
    }
    /**
     * Counts all the results collected by the iterators.
     */
    public function count(): int
    {
        return iterator_count($this->getIterator());
    }
    private function search_in_directory(string $dir): \Iterator
    {
        $exclude = $this->exclude;
        $not_paths = $this->not_paths;
        if ($this->prune_filters) {
            $exclude = array_merge($exclude, $this->prune_filters);
        }
        if (static::IGNORE_VCS_FILES === (static::IGNORE_VCS_FILES & $this->ignore)) {
            $exclude = array_merge($exclude, self::$vcs_patterns);
        }
        if (static::IGNORE_DOT_FILES === (static::IGNORE_DOT_FILES & $this->ignore)) {
            $not_paths[] = '#(^|/)\..+(/|$)#';
        }
        $min_depth = 0;
        $max_depth = \PHP_INT_MAX;
        foreach ($this->depths as $comparator) {
            switch ($comparator->get_operator()) {
                case '>':
                    $min_depth = $comparator->get_target() + 1;
                    break;
                case '>=':
                    $min_depth = $comparator->get_target();
                    break;
                case '<':
                    $max_depth = $comparator->get_target() - 1;
                    break;
                case '<=':
                    $max_depth = $comparator->get_target();
                    break;
                default:
                    $min_depth = $max_depth = $comparator->get_target();
            }
        }
        $flags = \Recursive_Directory_Iterator::SKIP_DOTS;
        if ($this->follow_links) {
            $flags |= \Recursive_Directory_Iterator::FOLLOW_SYMLINKS;
        }
        if ($this->unix_paths) {
            $flags |= \Recursive_Directory_Iterator::UNIX_PATHS;
        }
        $iterator = new Iterator\Recursive_Directory_Iterator($dir, $flags, $this->ignore_unreadable_dirs);
        if ($exclude) {
            $iterator = new Exclude_Directory_Filter_Iterator($iterator, $exclude);
        }
        $iterator = new \Recursive_Iterator_Iterator($iterator, \Recursive_Iterator_Iterator::SELF_FIRST);
        if ($min_depth > 0 || $max_depth < \PHP_INT_MAX) {
            $iterator = new Depth_Range_Filter_Iterator($iterator, $min_depth, $max_depth);
        }
        if ($this->mode) {
            $iterator = new Iterator\File_Type_Filter_Iterator($iterator, $this->mode);
        }
        if ($this->names || $this->not_names) {
            $iterator = new Filename_Filter_Iterator($iterator, $this->names, $this->not_names);
        }
        if ($this->contains || $this->not_contains) {
            $iterator = new Filecontent_Filter_Iterator($iterator, $this->contains, $this->not_contains);
        }
        if ($this->sizes) {
            $iterator = new Size_Range_Filter_Iterator($iterator, $this->sizes);
        }
        if ($this->dates) {
            $iterator = new Date_Range_Filter_Iterator($iterator, $this->dates);
        }
        if ($this->filters) {
            $iterator = new Custom_Filter_Iterator($iterator, $this->filters);
        }
        if ($this->paths || $not_paths) {
            $iterator = new Iterator\Path_Filter_Iterator($iterator, $this->paths, $not_paths);
        }
        if (static::IGNORE_VCS_IGNORED_FILES === (static::IGNORE_VCS_IGNORED_FILES & $this->ignore)) {
            return new Iterator\Vcs_Ignored_Filter_Iterator($iterator, $dir);
        }
        return $iterator;
    }
    /**
     * Normalizes given directory names by removing trailing slashes.
     *
     * Excluding: (s)ftp:// or ssh2.(s)ftp:// wrapper
     */
    private function normalize_dir(string $dir): string
    {
        if ('/' === $dir) {
            return $dir;
        }
        $dir = rtrim($dir, '/' . \DIRECTORY_SEPARATOR);
        if (preg_match('#^(ssh2\.)?s?ftp://#', $dir)) {
            $dir .= '/';
        }
        return $dir;
    }
}