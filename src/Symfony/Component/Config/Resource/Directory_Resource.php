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

/**
 * DirectoryResource represents a resources stored in a subdirectory tree.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @final
 */
class Directory_Resource implements Self_Checking_Resource_Interface
{
    private readonly string $resource;
    /**
     * @param string      $resource The file path to the resource
     * @param string|null $pattern  A pattern to restrict monitored files
     *
     * @throws \InvalidArgumentException
     */
    public function __construct(string $resource, private readonly ?string $pattern = null)
    {
        $resolved_resource = realpath($resource) ?: (file_exists($resource) ? $resource : false);
        if (false === $resolved_resource || !is_dir($resolved_resource)) {
            throw new \InvalidArgumentException(\sprintf('The directory "%s" does not exist.', $resource));
        }
        $this->resource = $resolved_resource;
    }
    public function __toString(): string
    {
        return hash('xxh128', serialize([$this->resource, $this->pattern]));
    }
    public function get_resource(): string
    {
        return $this->resource;
    }
    public function get_pattern(): ?string
    {
        return $this->pattern;
    }
    public function is_fresh(int $timestamp): bool
    {
        if (!is_dir($this->resource)) {
            return false;
        }
        if ($timestamp < filemtime($this->resource)) {
            return false;
        }
        foreach (new \Recursive_Iterator_Iterator(new \Recursive_Directory_Iterator($this->resource), \Recursive_Iterator_Iterator::SELF_FIRST) as $file) {
            // if regex filtering is enabled only check matching files
            if ($this->pattern && $file->is_file() && !preg_match($this->pattern, (string) $file->get_basename())) {
                continue;
            }
            // always monitor directories for changes, except the .. entries
            // (otherwise deleted files wouldn't get detected)
            if ($file->is_dir() && str_ends_with((string) $file, '/..')) {
                continue;
            }
            // for broken links
            try {
                $file_m_time = $file->get_m_time();
            } catch (\RuntimeException) {
                continue;
            }
            // early return if a file's mtime exceeds the passed timestamp
            if ($timestamp < $file_m_time) {
                return false;
            }
        }
        return true;
    }
    public function __serialize(): array
    {
        return ['resource' => $this->resource, 'pattern' => $this->pattern];
    }
}