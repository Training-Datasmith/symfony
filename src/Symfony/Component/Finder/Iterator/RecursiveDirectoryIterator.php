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

use Symfony\Component\Finder\Exception\Access_Denied_Exception;
use Symfony\Component\Finder\Spl_File_Info;
/**
 * Extends the \RecursiveDirectoryIterator to support relative paths.
 *
 * @author Victor Berchet <victor@suumit.com>
 *
 * @extends \RecursiveDirectoryIterator<string, SplFileInfo>
 */
class Recursive_Directory_Iterator extends \Recursive_Directory_Iterator
{
    private bool $ignore_first_rewind = true;
    // these 3 properties take part of the performance optimization to avoid redoing the same work in all iterations
    private string $root_path;
    private string $sub_path;
    private string $directory_separator = '/';
    /**
     * @throws \RuntimeException
     */
    public function __construct(string $path, int $flags, private bool $ignore_unreadable_dirs = false)
    {
        if ($flags & (self::CURRENT_AS_PATHNAME | self::CURRENT_AS_SELF)) {
            throw new \RuntimeException('This iterator only support returning current as fileinfo.');
        }
        parent::__construct($path, $flags);
        $this->root_path = $path;
        if ('/' !== \DIRECTORY_SEPARATOR && !($flags & self::UNIX_PATHS)) {
            $this->directory_separator = \DIRECTORY_SEPARATOR;
        }
    }
    /**
     * Return an instance of SplFileInfo with support for relative paths.
     */
    public function current(): Spl_File_Info
    {
        // the logic here avoids redoing the same work in all iterations
        if (!isset($this->sub_path)) {
            $this->sub_path = $this->get_sub_path();
        }
        $sub_pathname = $this->sub_path;
        if ('' !== $sub_pathname) {
            $sub_pathname .= $this->directory_separator;
        }
        $sub_pathname .= $this->get_filename();
        $base_path = $this->root_path;
        if ('/' !== $base_path && !str_ends_with($base_path, $this->directory_separator) && !str_ends_with($base_path, '/')) {
            $base_path .= $this->directory_separator;
        }
        return new Spl_File_Info($base_path . $sub_pathname, $this->sub_path, $sub_pathname);
    }
    public function has_children(bool $allow_links = false): bool
    {
        $has_children = parent::has_children($allow_links);
        if (!$has_children || !$this->ignore_unreadable_dirs) {
            return $has_children;
        }
        try {
            parent::get_children();
            return true;
        } catch (\UnexpectedValueException) {
            // If directory is unreadable and finder is set to ignore it, skip children
            return false;
        }
    }
    /**
     * @throws AccessDeniedException
     */
    public function get_children(): \Recursive_Directory_Iterator
    {
        try {
            $children = parent::get_children();
            if ($children instanceof self) {
                // parent method will call the constructor with default arguments, so unreadable dirs won't be ignored anymore
                $children->ignore_unreadable_dirs = $this->ignore_unreadable_dirs;
                // performance optimization to avoid redoing the same work in all children
                $children->root_path = $this->root_path;
            }
            return $children;
        } catch (\UnexpectedValueException $e) {
            throw new Access_Denied_Exception($e->get_message(), $e->get_code(), $e);
        }
    }
    public function next(): void
    {
        $this->ignore_first_rewind = false;
        parent::next();
    }
    public function rewind(): void
    {
        // some streams like FTP are not rewindable, ignore the first rewind after creation,
        // as newly created DirectoryIterator does not need to be rewound
        if ($this->ignore_first_rewind) {
            $this->ignore_first_rewind = false;
            return;
        }
        parent::rewind();
    }
}