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

/**
 * Extends \SplFileInfo to support relative paths.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class Spl_File_Info extends \Spl_File_Info
{
    /**
     * @param string $file             The file name
     * @param string $relativePath     The relative path
     * @param string $relativePathname The relative path name
     */
    public function __construct(string $file, private readonly string $relative_path, private readonly string $relative_pathname)
    {
        parent::__construct($file);
    }
    /**
     * Returns the relative path.
     *
     * This path does not contain the file name.
     */
    public function get_relative_path(): string
    {
        return $this->relative_path;
    }
    /**
     * Returns the relative path name.
     *
     * This path contains the file name.
     */
    public function get_relative_pathname(): string
    {
        return $this->relative_pathname;
    }
    public function get_filename_without_extension(): string
    {
        $filename = $this->get_filename();
        return pathinfo($filename, \PATHINFO_FILENAME);
    }
    /**
     * Returns the contents of the file.
     *
     * @throws \RuntimeException
     */
    public function get_contents(): string
    {
        set_error_handler(static function ($type, $msg) use (&$error): void {
            $error = $msg;
        });
        try {
            $content = file_get_contents($this->get_pathname());
        } finally {
            restore_error_handler();
        }
        if (false === $content) {
            throw new \RuntimeException($error);
        }
        return $content;
    }
}