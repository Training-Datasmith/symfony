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
namespace Symfony\Component\Config\Loader;

use Symfony\Component\Config\Exception\File_Loader_Import_Circular_Reference_Exception;
use Symfony\Component\Config\Exception\File_Locator_File_Not_Found_Exception;
use Symfony\Component\Config\Exception\Loader_Load_Exception;
use Symfony\Component\Config\File_Locator_Interface;
use Symfony\Component\Config\Resource\File_Existence_Resource;
use Symfony\Component\Config\Resource\Glob_Resource;
/**
 * FileLoader is the abstract class used by all built-in loaders that are file based.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
abstract class File_Loader extends Loader
{
    protected static array $loading = [];
    private ?string $current_dir = null;
    public function __construct(protected File_Locator_Interface $locator, ?string $env = null)
    {
        parent::__construct($env);
    }
    /**
     * Sets the current directory.
     */
    public function set_current_dir(string $dir): void
    {
        $this->current_dir = $dir;
    }
    /**
     * Returns the file locator used by this loader.
     */
    public function get_locator(): File_Locator_Interface
    {
        return $this->locator;
    }
    /**
     * Imports a resource.
     *
     * @param mixed                $resource       A Resource
     * @param string|null          $type           The resource type or null if unknown
     * @param bool                 $ignoreErrors   Whether to ignore import errors or not
     * @param string|null          $sourceResource The original resource importing the new resource
     * @param string|string[]|null $exclude        Glob patterns to exclude from the import
     *
     * @throws LoaderLoadException
     * @throws FileLoaderImportCircularReferenceException
     * @throws FileLocatorFileNotFoundException
     */
    public function import(mixed $resource, ?string $type = null, bool $ignore_errors = false, ?string $source_resource = null, string|array|null $exclude = null): mixed
    {
        $excluded = [];
        foreach ((array) $exclude as $pattern) {
            foreach ($this->glob($pattern, true, $_, false, true) as $path => $info) {
                // normalize Windows slashes and remove trailing slashes
                $excluded[rtrim(str_replace('\\', '/', $path), '/')] = true;
            }
        }
        if (\is_string($resource) && !class_exists($resource)) {
            $is_glob_pattern = \strlen($resource) !== strcspn($resource, '*?{[');
            if (!$is_glob_pattern && $excluded) {
                $resource = rtrim(str_replace('\\', '/', $resource), '/');
                $resource .= '/**/*';
                $is_glob_pattern = true;
            }
            if ($is_glob_pattern && !str_contains($resource, "\n")) {
                $ret = [];
                $i = strcspn($resource, '*?{[');
                $is_subpath = 0 !== $i && str_contains(substr($resource, 0, $i), '/');
                foreach ($this->glob($resource, false, $_, $ignore_errors || !$is_subpath, false, $excluded) as $path => $info) {
                    if (null !== $res = $this->do_import($path, 'glob' === $type ? null : $type, $ignore_errors, $source_resource)) {
                        $ret[] = $res;
                    }
                    $is_subpath = true;
                }
                if ($is_subpath) {
                    return isset($ret[1]) ? $ret : $ret[0] ?? null;
                }
            }
        } elseif (\is_array($resource) && $excluded) {
            $resource['_excluded'] = $excluded;
        }
        return $this->do_import($resource, $type, $ignore_errors, $source_resource);
    }
    /**
     * @internal
     */
    protected function glob(string $pattern, bool $recursive, array|Glob_Resource|null &$resource = null, bool $ignore_errors = false, bool $for_exclusion = false, array $excluded = []): iterable
    {
        if (\strlen($pattern) === $i = strcspn($pattern, '*?{[')) {
            $prefix = $pattern;
            $pattern = '';
        } elseif (0 === $i || !str_contains(substr($pattern, 0, $i), '/')) {
            $prefix = '.';
            $pattern = '/' . $pattern;
        } else {
            $prefix = \dirname(substr($pattern, 0, 1 + $i));
            $pattern = substr($pattern, \strlen($prefix));
        }
        try {
            $prefix = $this->locator->locate($prefix, $this->current_dir, true);
        } catch (File_Locator_File_Not_Found_Exception $e) {
            if (!$ignore_errors) {
                throw $e;
            }
            $resource = [];
            foreach ($e->get_paths() as $path) {
                $resource[] = new File_Existence_Resource($path);
            }
            return;
        }
        $resource = new Glob_Resource($prefix, $pattern, $recursive, $for_exclusion, $excluded);
        yield from $resource;
    }
    private function do_import(mixed $resource, ?string $type = null, bool $ignore_errors = false, ?string $source_resource = null): mixed
    {
        try {
            $loader = $this->resolve($resource, $type);
            if ($loader instanceof Directory_Aware_Loader_Interface) {
                $loader = $loader->for_directory($this->current_dir);
            }
            if (!$loader instanceof self) {
                return $loader->load($resource, $type);
            }
            if (null !== $this->current_dir) {
                $resource = $loader->get_locator()->locate($resource, $this->current_dir, false);
            }
            $resources = \is_array($resource) ? $resource : [$resource];
            for ($i = 0; $i < $resources_count = \count($resources); ++$i) {
                if (isset(self::$loading[$resources[$i]])) {
                    if ($i == $resources_count - 1) {
                        throw new File_Loader_Import_Circular_Reference_Exception(array_keys(self::$loading));
                    }
                } else {
                    $resource = $resources[$i];
                    break;
                }
            }
            self::$loading[$resource] = true;
            try {
                $ret = $loader->load($resource, $type);
            } finally {
                unset(self::$loading[$resource]);
            }
            return $ret;
        } catch (File_Loader_Import_Circular_Reference_Exception $e) {
            throw $e;
        } catch (\Exception $e) {
            if (!$ignore_errors) {
                // prevent embedded imports from nesting multiple exceptions
                if ($e instanceof Loader_Load_Exception) {
                    throw $e;
                }
                throw new Loader_Load_Exception($resource, $source_resource, 0, $e, $type);
            }
        }
        return null;
    }
}