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
namespace Symfony\Component\Http_Foundation\File;

use Symfony\Component\Http_Foundation\File\Exception\File_Exception;
use Symfony\Component\Http_Foundation\File\Exception\File_Not_Found_Exception;
use Symfony\Component\Mime\Mime_Types;
/**
 * A file in the file system.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class File extends \Spl_File_Info
{
    /**
     * Constructs a new file from the given path.
     *
     * @param string $path      The path to the file
     * @param bool   $checkPath Whether to check the path or not
     *
     * @throws FileNotFoundException If the given path is not a file
     */
    public function __construct(string $path, bool $check_path = true)
    {
        if ($check_path && !is_file($path)) {
            throw new File_Not_Found_Exception($path);
        }
        parent::__construct($path);
    }
    /**
     * Returns the extension based on the mime type.
     *
     * If the mime type is unknown, returns null.
     *
     * This method uses the mime type as guessed by getMimeType()
     * to guess the file extension.
     *
     * @see MimeTypes
     * @see getMimeType()
     */
    public function guess_extension(): ?string
    {
        if (!class_exists(Mime_Types::class)) {
            throw new \LogicException('You cannot guess the extension as the Mime component is not installed. Try running "composer require symfony/mime".');
        }
        return Mime_Types::get_default()->get_extensions($this->get_mime_type())[0] ?? null;
    }
    /**
     * Returns the mime type of the file.
     *
     * The mime type is guessed using a MimeTypeGuesserInterface instance,
     * which uses finfo_file() then the "file" system binary,
     * depending on which of those are available.
     *
     * @see MimeTypes
     */
    public function get_mime_type(): ?string
    {
        if (!class_exists(Mime_Types::class)) {
            throw new \LogicException('You cannot guess the mime type as the Mime component is not installed. Try running "composer require symfony/mime".');
        }
        return Mime_Types::get_default()->guess_mime_type($this->get_pathname());
    }
    /**
     * Moves the file to a new location.
     *
     * @throws FileException if the target file could not be created
     */
    public function move(string $directory, ?string $name = null): self
    {
        $target = $this->get_target_file($directory, $name);
        set_error_handler(static function ($type, $msg) use (&$error): void {
            $error = $msg;
        });
        try {
            $renamed = rename($this->get_pathname(), $target);
        } finally {
            restore_error_handler();
        }
        if (!$renamed) {
            throw new File_Exception(\sprintf('Could not move the file "%s" to "%s" (%s).', $this->get_pathname(), $target, strip_tags((string) $error)));
        }
        @chmod($target, 0666 & ~umask());
        return $target;
    }
    public function get_content(): string
    {
        $content = file_get_contents($this->get_pathname());
        if (false === $content) {
            throw new File_Exception(\sprintf('Could not get the content of the file "%s".', $this->get_pathname()));
        }
        return $content;
    }
    protected function get_target_file(string $directory, ?string $name = null): self
    {
        if (!is_dir($directory) && !@mkdir($directory, 0777, true) && !is_dir($directory)) {
            if (is_file($directory)) {
                throw new File_Exception(\sprintf('Unable to create the "%s" directory: a similarly-named file exists.', $directory));
            }
            throw new File_Exception(\sprintf('Unable to create the "%s" directory.', $directory));
        }
        if (!is_writable($directory)) {
            throw new File_Exception(\sprintf('Unable to write in the "%s" directory.', $directory));
        }
        $target = rtrim($directory, '/\\') . \DIRECTORY_SEPARATOR . (null === $name ? $this->get_basename() : $this->get_name($name));
        return new self($target, false);
    }
    /**
     * Returns locale independent base name of the given path.
     */
    protected function get_name(string $name): string
    {
        $original_name = str_replace('\\', '/', $name);
        $pos = strrpos($original_name, '/');
        return false === $pos ? $original_name : substr($original_name, $pos + 1);
    }
}