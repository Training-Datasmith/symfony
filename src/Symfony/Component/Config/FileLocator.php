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

use Symfony\Component\Config\Exception\File_Locator_File_Not_Found_Exception;
/**
 * FileLocator uses an array of pre-defined paths to find files.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class File_Locator implements File_Locator_Interface
{
    protected array $paths;
    /**
     * @param string|string[] $paths A path or an array of paths where to look for resources
     */
    public function __construct(string|array $paths = [])
    {
        $this->paths = (array) $paths;
    }
    /**
     * @return string|string[]
     *
     * @psalm-return ($first is true ? string : string[])
     */
    public function locate(string $name, ?string $current_path = null, bool $first = true): string|array
    {
        if ('' === $name) {
            throw new \InvalidArgumentException('An empty file name is not valid to be located.');
        }
        if ($this->is_absolute_path($name)) {
            if (!file_exists($name)) {
                throw new File_Locator_File_Not_Found_Exception(\sprintf('The file "%s" does not exist.', $name), 0, null, [$name]);
            }
            return $name;
        }
        $paths = $this->paths;
        if (null !== $current_path) {
            array_unshift($paths, $current_path);
        }
        $paths = array_unique($paths);
        $filepaths = $notfound = [];
        foreach ($paths as $path) {
            if (@file_exists($file = $path . \DIRECTORY_SEPARATOR . $name)) {
                if (true === $first) {
                    return $file;
                }
                $filepaths[] = $file;
            } else {
                $notfound[] = $file;
            }
        }
        if (!$filepaths) {
            throw new File_Locator_File_Not_Found_Exception(\sprintf('The file "%s" does not exist (in: "%s").', $name, implode('", "', $paths)), 0, null, $notfound);
        }
        return $filepaths;
    }
    /**
     * Returns whether the file path is an absolute path.
     */
    private function is_absolute_path(string $file): bool
    {
        if ('/' === $file[0] || '\\' === $file[0] || \strlen($file) > 3 && ctype_alpha($file[0]) && ':' === $file[1] && ('\\' === $file[2] || '/' === $file[2]) || parse_url($file, \PHP_URL_SCHEME) || str_starts_with($file, 'phar:///')) {
            return true;
        }
        return false;
    }
}