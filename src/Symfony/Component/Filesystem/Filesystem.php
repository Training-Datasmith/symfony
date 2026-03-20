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
namespace Symfony\Component\Filesystem;

use Symfony\Component\Filesystem\Exception\File_Not_Found_Exception;
use Symfony\Component\Filesystem\Exception\InvalidArgumentException;
use Symfony\Component\Filesystem\Exception\Io_Exception;
/**
 * Provides basic utility to manipulate the file system.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class Filesystem
{
    private static ?string $last_error = null;
    /**
     * Copies a file.
     *
     * If the target file is older than the origin file, it's always overwritten.
     * If the target file is newer, it is overwritten only when the
     * $overwriteNewerFiles option is set to true.
     *
     * @throws FileNotFoundException When originFile doesn't exist
     * @throws IOException           When copy fails
     */
    public function copy(string $origin_file, string $target_file, bool $overwrite_newer_files = false): void
    {
        $origin_is_local = stream_is_local($origin_file) || 0 === stripos($origin_file, 'file://');
        if ($origin_is_local && !is_file($origin_file)) {
            throw new File_Not_Found_Exception(\sprintf('Failed to copy "%s" because file does not exist.', $origin_file), 0, null, $origin_file);
        }
        $this->mkdir(\dirname($target_file));
        $do_copy = true;
        if (!$overwrite_newer_files && !parse_url($origin_file, \PHP_URL_HOST) && is_file($target_file)) {
            $do_copy = filemtime($origin_file) > filemtime($target_file);
        }
        if ($do_copy) {
            // https://bugs.php.net/64634
            if (!$source = self::box('fopen', $origin_file, 'r')) {
                throw new Io_Exception(\sprintf('Failed to copy "%s" to "%s" because source file could not be opened for reading: ', $origin_file, $target_file) . self::$last_error, 0, null, $origin_file);
            }
            // Stream context created to allow files overwrite when using FTP stream wrapper - disabled by default
            if (!$target = self::box('fopen', $target_file, 'w', false, stream_context_create(['ftp' => ['overwrite' => true]]))) {
                throw new Io_Exception(\sprintf('Failed to copy "%s" to "%s" because target file could not be opened for writing: ', $origin_file, $target_file) . self::$last_error, 0, null, $origin_file);
            }
            $bytes_copied = stream_copy_to_stream($source, $target);
            fclose($source);
            fclose($target);
            unset($source, $target);
            if (!is_file($target_file)) {
                throw new Io_Exception(\sprintf('Failed to copy "%s" to "%s".', $origin_file, $target_file), 0, null, $origin_file);
            }
            if ($origin_is_local) {
                // Like `cp`, preserve executable permission bits
                self::box('chmod', $target_file, fileperms($target_file) | fileperms($origin_file) & 0111);
                // Like `cp`, preserve the file modification time
                self::box('touch', $target_file, filemtime($origin_file));
                if ($bytes_copied !== $bytes_origin = filesize($origin_file)) {
                    throw new Io_Exception(\sprintf('Failed to copy the whole content of "%s" to "%s" (%g of %g bytes copied).', $origin_file, $target_file, $bytes_copied, $bytes_origin), 0, null, $origin_file);
                }
            }
        }
    }
    /**
     * Creates a directory recursively.
     *
     * @throws IOException On any directory creation failure
     */
    public function mkdir(string|iterable $dirs, int $mode = 0777): void
    {
        foreach ($this->to_iterable($dirs) as $dir) {
            if (is_dir($dir)) {
                continue;
            }
            if (!self::box('mkdir', $dir, $mode, true) && !is_dir($dir)) {
                throw new Io_Exception(\sprintf('Failed to create "%s": ', $dir) . self::$last_error, 0, null, $dir);
            }
        }
    }
    /**
     * Checks the existence of files or directories.
     */
    public function exists(string|iterable $files): bool
    {
        $max_path_length = \PHP_MAXPATHLEN - 2;
        foreach ($this->to_iterable($files) as $file) {
            if (\strlen((string) $file) > $max_path_length) {
                throw new Io_Exception(\sprintf('Could not check if file exist because path length exceeds %d characters.', $max_path_length), 0, null, $file);
            }
            if (!file_exists($file)) {
                return false;
            }
        }
        return true;
    }
    /**
     * Sets access and modification time of file.
     *
     * @param int|null $time  The touch time as a Unix timestamp, if not supplied the current system time is used
     * @param int|null $atime The access time as a Unix timestamp, if not supplied the current system time is used
     *
     * @throws IOException When touch fails
     */
    public function touch(string|iterable $files, ?int $time = null, ?int $atime = null): void
    {
        foreach ($this->to_iterable($files) as $file) {
            if (!($time ? self::box('touch', $file, $time, $atime) : self::box('touch', $file))) {
                throw new Io_Exception(\sprintf('Failed to touch "%s": ', $file) . self::$last_error, 0, null, $file);
            }
        }
    }
    /**
     * Removes files or directories.
     *
     * @throws IOException When removal fails
     */
    public function remove(string|iterable $files): void
    {
        if ($files instanceof \Traversable) {
            $files = iterator_to_array($files, false);
        } elseif (!\is_array($files)) {
            $files = [$files];
        }
        self::do_remove($files, false);
    }
    private static function do_remove(array $files, bool $is_recursive): void
    {
        $files = array_reverse($files);
        foreach ($files as $file) {
            if (is_link($file)) {
                // See https://bugs.php.net/52176
                if (!(self::box('unlink', $file) || '\\' !== \DIRECTORY_SEPARATOR || self::box('rmdir', $file)) && file_exists($file)) {
                    throw new Io_Exception(\sprintf('Failed to remove symlink "%s": ', $file) . self::$last_error);
                }
            } elseif (is_dir($file)) {
                if (!$is_recursive) {
                    $tmp_name = \dirname(realpath($file)) . '/.!' . strrev(strtr(base64_encode(random_bytes(2)), '/=', '-!'));
                    if (file_exists($tmp_name)) {
                        try {
                            self::do_remove([$tmp_name], true);
                        } catch (Io_Exception) {
                        }
                    }
                    if (!file_exists($tmp_name) && self::box('rename', $file, $tmp_name)) {
                        $orig_file = $file;
                        $file = $tmp_name;
                    } else {
                        $orig_file = null;
                    }
                }
                $filesystem_iterator = new \Filesystem_Iterator($file, \Filesystem_Iterator::CURRENT_AS_PATHNAME | \Filesystem_Iterator::SKIP_DOTS);
                self::do_remove(iterator_to_array($filesystem_iterator, true), true);
                if (!self::box('rmdir', $file) && file_exists($file) && !$is_recursive) {
                    $last_error = self::$last_error;
                    if (null !== $orig_file && self::box('rename', $file, $orig_file)) {
                        $file = $orig_file;
                    }
                    throw new Io_Exception(\sprintf('Failed to remove directory "%s": ', $file) . $last_error);
                }
            } elseif (!self::box('unlink', $file) && (self::$last_error && str_contains(self::$last_error, 'Permission denied') || file_exists($file))) {
                throw new Io_Exception(\sprintf('Failed to remove file "%s": ', $file) . self::$last_error);
            }
        }
    }
    /**
     * Change mode for an array of files or directories.
     *
     * @param int  $mode      The new mode (octal)
     * @param int  $umask     The mode mask (octal)
     * @param bool $recursive Whether change the mod recursively or not
     *
     * @throws IOException When the change fails
     */
    public function chmod(string|iterable $files, int $mode, int $umask = 00, bool $recursive = false): void
    {
        foreach ($this->to_iterable($files) as $file) {
            if (!self::box('chmod', $file, $mode & ~$umask)) {
                throw new Io_Exception(\sprintf('Failed to chmod file "%s": ', $file) . self::$last_error, 0, null, $file);
            }
            if ($recursive && is_dir($file) && !is_link($file)) {
                $this->chmod(new \Filesystem_Iterator($file), $mode, $umask, true);
            }
        }
    }
    /**
     * Change the owner of an array of files or directories.
     *
     * This method always throws on Windows, as the underlying PHP function is not supported.
     *
     * @see https://php.net/chown
     *
     * @param string|int $user      A user name or number
     * @param bool       $recursive Whether change the owner recursively or not
     *
     * @throws IOException When the change fails
     */
    public function chown(string|iterable $files, string|int $user, bool $recursive = false): void
    {
        foreach ($this->to_iterable($files) as $file) {
            if ($recursive && is_dir($file) && !is_link($file)) {
                $this->chown(new \Filesystem_Iterator($file), $user, true);
            }
            if (is_link($file) && \function_exists('lchown')) {
                if (!self::box('lchown', $file, $user)) {
                    throw new Io_Exception(\sprintf('Failed to chown file "%s": ', $file) . self::$last_error, 0, null, $file);
                }
            } else if (!self::box('chown', $file, $user)) {
                throw new Io_Exception(\sprintf('Failed to chown file "%s": ', $file) . self::$last_error, 0, null, $file);
            }
        }
    }
    /**
     * Change the group of an array of files or directories.
     *
     * This method always throws on Windows, as the underlying PHP function is not supported.
     *
     * @see https://php.net/chgrp
     *
     * @param string|int $group     A group name or number
     * @param bool       $recursive Whether change the group recursively or not
     *
     * @throws IOException When the change fails
     */
    public function chgrp(string|iterable $files, string|int $group, bool $recursive = false): void
    {
        foreach ($this->to_iterable($files) as $file) {
            if ($recursive && is_dir($file) && !is_link($file)) {
                $this->chgrp(new \Filesystem_Iterator($file), $group, true);
            }
            if (is_link($file) && \function_exists('lchgrp')) {
                if (!self::box('lchgrp', $file, $group)) {
                    throw new Io_Exception(\sprintf('Failed to chgrp file "%s": ', $file) . self::$last_error, 0, null, $file);
                }
            } else if (!self::box('chgrp', $file, $group)) {
                throw new Io_Exception(\sprintf('Failed to chgrp file "%s": ', $file) . self::$last_error, 0, null, $file);
            }
        }
    }
    /**
     * Renames a file or a directory.
     *
     * @throws IOException When target file or directory already exists
     * @throws IOException When origin cannot be renamed
     */
    public function rename(string $origin, string $target, bool $overwrite = false): void
    {
        // we check that target does not exist
        if (!$overwrite && $this->is_readable($target)) {
            throw new Io_Exception(\sprintf('Cannot rename because the target "%s" already exists.', $target), 0, null, $target);
        }
        if (!self::box('rename', $origin, $target)) {
            if (is_dir($origin)) {
                // See https://bugs.php.net/54097 & https://php.net/rename#113943
                $this->mirror($origin, $target, null, ['override' => $overwrite, 'delete' => $overwrite]);
                $this->remove($origin);
                return;
            }
            throw new Io_Exception(\sprintf('Cannot rename "%s" to "%s": ', $origin, $target) . self::$last_error, 0, null, $target);
        }
    }
    /**
     * Tells whether a file exists and is readable.
     *
     * @throws IOException When windows path is longer than 258 characters
     */
    private function is_readable(string $filename): bool
    {
        $max_path_length = \PHP_MAXPATHLEN - 2;
        if (\strlen($filename) > $max_path_length) {
            throw new Io_Exception(\sprintf('Could not check if file is readable because path length exceeds %d characters.', $max_path_length), 0, null, $filename);
        }
        return is_readable($filename);
    }
    /**
     * Creates a symbolic link or copy a directory.
     *
     * @throws IOException When symlink fails
     */
    public function symlink(string $origin_dir, string $target_dir, bool $copy_on_windows = false): void
    {
        self::assert_function_exists('symlink');
        if ('\\' === \DIRECTORY_SEPARATOR) {
            $origin_dir = strtr($origin_dir, '/', '\\');
            $target_dir = strtr($target_dir, '/', '\\');
            if ($copy_on_windows) {
                $this->mirror($origin_dir, $target_dir);
                return;
            }
        }
        $this->mkdir(\dirname($target_dir));
        if (is_link($target_dir)) {
            if (readlink($target_dir) === $origin_dir) {
                return;
            }
            $this->remove($target_dir);
        }
        if (!self::box('symlink', $origin_dir, $target_dir)) {
            $this->link_exception($origin_dir, $target_dir, 'symbolic');
        }
    }
    /**
     * Creates a hard link, or several hard links to a file.
     *
     * @param string|string[] $targetFiles The target file(s)
     *
     * @throws FileNotFoundException When original file is missing or not a file
     * @throws IOException           When link fails, including if link already exists
     */
    public function hardlink(string $origin_file, string|iterable $target_files): void
    {
        self::assert_function_exists('link');
        if (!$this->exists($origin_file)) {
            throw new File_Not_Found_Exception(null, 0, null, $origin_file);
        }
        if (!is_file($origin_file)) {
            throw new File_Not_Found_Exception(\sprintf('Origin file "%s" is not a file.', $origin_file));
        }
        foreach ($this->to_iterable($target_files) as $target_file) {
            if (is_file($target_file)) {
                if (fileinode($origin_file) === fileinode($target_file)) {
                    continue;
                }
                $this->remove($target_file);
            }
            if (!self::box('link', $origin_file, $target_file)) {
                $this->link_exception($origin_file, $target_file, 'hard');
            }
        }
    }
    /**
     * @param string $linkType Name of the link type, typically 'symbolic' or 'hard'
     */
    private function link_exception(string $origin, string $target, string $link_type): never
    {
        if (self::$last_error) {
            if ('\\' === \DIRECTORY_SEPARATOR && str_contains(self::$last_error, 'error code(1314)')) {
                throw new Io_Exception(\sprintf('Unable to create "%s" link due to error code 1314: \'A required privilege is not held by the client\'. Do you have the required Administrator-rights?', $link_type), 0, null, $target);
            }
        }
        throw new Io_Exception(\sprintf('Failed to create "%s" link from "%s" to "%s": ', $link_type, $origin, $target) . self::$last_error, 0, null, $target);
    }
    /**
     * Resolves links in paths.
     *
     * With $canonicalize = false (default)
     *      - if $path does not exist or is not a link, returns null
     *      - if $path is a link, returns the next direct target of the link without considering the existence of the target
     *
     * With $canonicalize = true
     *      - if $path does not exist, returns null
     *      - if $path exists, returns its absolute fully resolved final version
     */
    public function readlink(string $path, bool $canonicalize = false): ?string
    {
        if (!$canonicalize && !is_link($path)) {
            return null;
        }
        if ($canonicalize) {
            if (!$this->exists($path)) {
                return null;
            }
            return realpath($path);
        }
        return readlink($path);
    }
    /**
     * Given an existing path, convert it to a path relative to a given starting path.
     */
    public function make_path_relative(string $end_path, string $start_path): string
    {
        if (!$this->is_absolute_path($start_path)) {
            throw new InvalidArgumentException(\sprintf('The start path "%s" is not absolute.', $start_path));
        }
        if (!$this->is_absolute_path($end_path)) {
            throw new InvalidArgumentException(\sprintf('The end path "%s" is not absolute.', $end_path));
        }
        $original_end_path = $end_path;
        // Normalize separators on Windows
        if ('\\' === \DIRECTORY_SEPARATOR) {
            $end_path = str_replace('\\', '/', $end_path);
            $start_path = str_replace('\\', '/', $start_path);
        }
        $split_drive_letter = static fn($path): array => \strlen((string) $path) > 2 && ':' === $path[1] && '/' === $path[2] && ctype_alpha((string) $path[0]) ? [substr((string) $path, 2), strtoupper((string) $path[0])] : [$path, null];
        $split_path = static function ($path): array {
            $result = [];
            foreach (explode('/', trim($path, '/')) as $segment) {
                if ('..' === $segment) {
                    array_pop($result);
                } elseif ('.' !== $segment && '' !== $segment) {
                    $result[] = $segment;
                }
            }
            return $result;
        };
        [$end_path, $end_drive_letter] = $split_drive_letter($end_path);
        [$start_path, $start_drive_letter] = $split_drive_letter($start_path);
        $start_path_arr = $split_path($start_path);
        $end_path_arr = $split_path($end_path);
        if ($end_drive_letter && $start_drive_letter && $end_drive_letter != $start_drive_letter) {
            // End path is on another drive, so no relative path exists
            return $end_drive_letter . ':/' . ($end_path_arr ? implode('/', $end_path_arr) . '/' : '');
        }
        // Find for which directory the common path stops
        $index = 0;
        while (isset($start_path_arr[$index]) && isset($end_path_arr[$index]) && $start_path_arr[$index] === $end_path_arr[$index]) {
            ++$index;
        }
        // Determine how deep the start path is relative to the common path (ie, "web/bundles" = 2 levels)
        if (1 === \count($start_path_arr) && '' === $start_path_arr[0]) {
            $depth = 0;
        } else {
            $depth = \count($start_path_arr) - $index;
        }
        // Repeated "../" for each level need to reach the common path
        $traverser = str_repeat('../', $depth);
        $end_path_remainder = implode('/', \array_slice($end_path_arr, $index));
        // Construct $endPath from traversing to the common path, then to the remaining $endPath
        $relative_path = $traverser . ('' !== $end_path_remainder ? $end_path_remainder . '/' : '');
        // Remove ending "/" if $endPath points to an existing file
        if (str_ends_with($relative_path, '/') && is_file($original_end_path)) {
            $relative_path = substr($relative_path, 0, -1);
        }
        return '' === $relative_path ? './' : $relative_path;
    }
    /**
     * Mirrors a directory to another.
     *
     * Copies files and directories from the origin directory into the target directory. By default:
     *
     *  - existing files in the target directory will be overwritten, except if they are newer (see the `override` option)
     *  - files in the target directory that do not exist in the source directory will not be deleted (see the `delete` option)
     *
     * @param \Traversable|null $iterator Iterator that filters which files and directories to copy, if null a recursive iterator is created
     * @param array             $options  An array of boolean options
     *                                    Valid options are:
     *                                    - $options['override'] If true, target files newer than origin files are overwritten (see copy(), defaults to false)
     *                                    - $options['follow_symlinks'] Whether to copy files instead of links, esp. useful on Windows (see symlink(), defaults to false)
     *                                    - $options['delete'] Whether to delete files that are not in the source directory (defaults to false)
     *
     * @throws IOException When file type is unknown
     */
    public function mirror(string $origin_dir, string $target_dir, ?\Traversable $iterator = null, array $options = []): void
    {
        if (isset($options['copy_on_windows'])) {
            trigger_deprecation('symfony/filesystem', '8.1', 'Calling "%s()" with option "copy_on_windows" is deprecated, use "follow_symlinks" option instead.', __METHOD__);
        }
        $target_dir = rtrim($target_dir, '/\\');
        $origin_dir = rtrim($origin_dir, '/\\');
        $origin_dir_len = \strlen($origin_dir);
        if (!$this->exists($origin_dir)) {
            throw new Io_Exception(\sprintf('The origin directory specified "%s" was not found.', $origin_dir), 0, null, $origin_dir);
        }
        // Iterate in destination folder to remove obsolete entries
        if ($this->exists($target_dir) && isset($options['delete']) && $options['delete']) {
            $delete_iterator = $iterator;
            if (null === $delete_iterator) {
                $flags = \Filesystem_Iterator::SKIP_DOTS;
                $delete_iterator = new \Recursive_Iterator_Iterator(new \Recursive_Directory_Iterator($target_dir, $flags), \Recursive_Iterator_Iterator::CHILD_FIRST);
            }
            $target_dir_len = \strlen($target_dir);
            foreach ($delete_iterator as $file) {
                $origin = $origin_dir . substr((string) $file->get_pathname(), $target_dir_len);
                if (!$this->exists($origin)) {
                    $this->remove($file);
                }
            }
        }
        $follow_symlinks = $options['follow_symlinks'] ?? $options['copy_on_windows'] ?? false;
        if (null === $iterator) {
            $flags = $follow_symlinks ? \Filesystem_Iterator::SKIP_DOTS | \Filesystem_Iterator::FOLLOW_SYMLINKS : \Filesystem_Iterator::SKIP_DOTS;
            $iterator = new \Recursive_Iterator_Iterator(new \Recursive_Directory_Iterator($origin_dir, $flags), \Recursive_Iterator_Iterator::SELF_FIRST);
        }
        $this->mkdir($target_dir);
        $files_created_while_mirroring = [];
        foreach ($iterator as $file) {
            if ($file->get_pathname() === $target_dir) {
                continue;
            }
            if ($file->get_real_path() === $target_dir) {
                continue;
            }
            if (isset($files_created_while_mirroring[$file->get_real_path()])) {
                continue;
            }
            $target = $target_dir . substr((string) $file->get_pathname(), $origin_dir_len);
            $files_created_while_mirroring[$target] = true;
            if (!$follow_symlinks && is_link($file)) {
                $this->symlink($file->get_link_target(), $target);
            } elseif (is_dir($file)) {
                $this->mkdir($target);
            } elseif (is_file($file)) {
                $this->copy($file, $target, $options['override'] ?? false);
            } else {
                throw new Io_Exception(\sprintf('Unable to guess "%s" file type.', $file), 0, null, $file);
            }
        }
    }
    /**
     * Returns whether the given path is absolute.
     */
    public function is_absolute_path(string $file): bool
    {
        return Path::is_absolute($file);
    }
    /**
     * Creates a temporary file with support for custom stream wrappers.
     *
     * @param string $prefix The prefix of the generated temporary filename
     *                       Note: Windows uses only the first three characters of prefix
     * @param string $suffix The suffix of the generated temporary filename
     *
     * @return string The new temporary filename (with path), or throw an exception on failure
     */
    public function tempnam(string $dir, string $prefix, string $suffix = ''): string
    {
        [$scheme, $hierarchy] = $this->get_scheme_and_hierarchy($dir);
        // If no scheme or scheme is "file" or "gs" (Google Cloud) create temp file in local filesystem
        if ((null === $scheme || 'file' === $scheme || 'gs' === $scheme) && '' === $suffix) {
            // If tempnam failed or no scheme return the filename otherwise prepend the scheme
            if ($tmp_file = self::box('tempnam', $hierarchy, $prefix)) {
                if (null !== $scheme && 'gs' !== $scheme) {
                    return $scheme . '://' . $tmp_file;
                }
                return $tmp_file;
            }
            throw new Io_Exception('A temporary file could not be created: ' . self::$last_error);
        }
        // Loop until we create a valid temp file or have reached 10 attempts
        for ($i = 0; $i < 10; ++$i) {
            // Create a unique filename
            $tmp_file = $dir . '/' . $prefix . bin2hex(random_bytes(4)) . $suffix;
            // Use fopen instead of file_exists as some streams do not support stat
            // Use mode 'x+' to atomically check existence and create to avoid a TOCTOU vulnerability
            if (!$handle = self::box('fopen', $tmp_file, 'x+')) {
                continue;
            }
            // Close the file if it was successfully opened
            self::box('fclose', $handle);
            return $tmp_file;
        }
        throw new Io_Exception('A temporary file could not be created: ' . self::$last_error);
    }
    /**
     * Atomically dumps content into a file.
     *
     * @param string|resource $content The data to write into the file
     *
     * @throws IOException if the file cannot be written to
     */
    public function dump_file(string $filename, $content): void
    {
        if (\is_array($content)) {
            throw new \TypeError(\sprintf('Argument 2 passed to "%s()" must be string or resource, array given.', __METHOD__));
        }
        $dir = \dirname($filename);
        if (is_link($filename) && $link_target = $this->readlink($filename)) {
            $this->dump_file(Path::make_absolute($link_target, $dir), $content);
            return;
        }
        if (!is_dir($dir)) {
            $this->mkdir($dir);
        }
        // Will create a temp file with 0600 access rights
        // when the filesystem supports chmod.
        $tmp_file = $this->tempnam($dir, basename($filename));
        try {
            if (false === self::box('file_put_contents', $tmp_file, $content)) {
                throw new Io_Exception(\sprintf('Failed to write file "%s": ', $filename) . self::$last_error, 0, null, $filename);
            }
            self::box('chmod', $tmp_file, self::box('fileperms', $filename) ?: 0666 & ~umask());
            $this->rename($tmp_file, $filename, true);
        } finally {
            if (file_exists($tmp_file)) {
                if ('\\' === \DIRECTORY_SEPARATOR && !is_writable($tmp_file)) {
                    self::box('chmod', $tmp_file, self::box('fileperms', $tmp_file) | 0200);
                }
                self::box('unlink', $tmp_file);
            }
        }
    }
    /**
     * Appends content to an existing file.
     *
     * @param string|resource $content The content to append
     * @param bool            $lock    Whether the file should be locked when writing to it
     *
     * @throws IOException If the file is not writable
     */
    public function append_to_file(string $filename, $content, bool $lock = false): void
    {
        if (\is_array($content)) {
            throw new \TypeError(\sprintf('Argument 2 passed to "%s()" must be string or resource, array given.', __METHOD__));
        }
        $dir = \dirname($filename);
        if (!is_dir($dir)) {
            $this->mkdir($dir);
        }
        if (false === self::box('file_put_contents', $filename, $content, \FILE_APPEND | ($lock ? \LOCK_EX : 0))) {
            throw new Io_Exception(\sprintf('Failed to write file "%s": ', $filename) . self::$last_error, 0, null, $filename);
        }
    }
    /**
     * Returns the content of a file as a string.
     *
     * @throws IOException If the file cannot be read
     */
    public function read_file(string $filename): string
    {
        if (is_dir($filename)) {
            throw new Io_Exception(\sprintf('Failed to read file "%s": File is a directory.', $filename));
        }
        $content = self::box('file_get_contents', $filename);
        if (false === $content) {
            throw new Io_Exception(\sprintf('Failed to read file "%s": ', $filename) . self::$last_error, 0, null, $filename);
        }
        return $content;
    }
    private function to_iterable(string|iterable $files): iterable
    {
        return is_iterable($files) ? $files : [$files];
    }
    /**
     * Gets a 2-tuple of scheme (may be null) and hierarchical part of a filename (e.g. file:///tmp -> [file, tmp]).
     */
    private function get_scheme_and_hierarchy(string $filename): array
    {
        $components = explode('://', $filename, 2);
        return 2 === \count($components) ? [$components[0], $components[1]] : [null, $components[0]];
    }
    private static function assert_function_exists(string $func): void
    {
        if (!\function_exists($func)) {
            throw new Io_Exception(\sprintf('Unable to perform filesystem operation because the "%s()" function has been disabled.', $func));
        }
    }
    private static function box(string $func, mixed ...$args): mixed
    {
        self::assert_function_exists($func);
        self::$last_error = null;
        set_error_handler(self::handle_error(...));
        try {
            return $func(...$args);
        } finally {
            restore_error_handler();
        }
    }
    /**
     * @internal
     */
    public static function handle_error(int $type, string $msg): void
    {
        self::$last_error = $msg;
    }
}