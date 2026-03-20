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
namespace Symfony\Component\Console\Input\File;

use Symfony\Component\Console\Exception\Invalid_File_Exception;
use Symfony\Component\Mime\Mime_Types;
/**
 * Represents a file provided through console input.
 *
 * Inspired by HttpFoundation's UploadedFile, this class wraps a file provided
 * through console input (either pasted via terminal image protocols or typed as a path).
 *
 * @author Robin Chalas <robin.chalas@gmail.com>
 */
final class Input_File extends \Spl_File_Info
{
    /** @var string[] */
    private static array $temp_files = [];
    private static bool $shutdown_registered = false;
    public function __construct(string $path, private readonly bool $is_temp_file = false, private ?string $mime_type = null)
    {
        parent::__construct($path);
        if ($this->is_temp_file) {
            if (!self::$shutdown_registered) {
                register_shutdown_function(self::cleanup_all(...));
                self::$shutdown_registered = true;
            }
            self::$temp_files[$path] = $path;
        }
    }
    /**
     * @throws InvalidFileException when the temporary file cannot be created
     */
    public static function from_data(string $data, ?string $format = null): self
    {
        $extension = $format ? '.' . $format : '';
        $temp_path = sys_get_temp_dir() . '/symfony_input_' . bin2hex(random_bytes(8)) . $extension;
        if (false === @file_put_contents($temp_path, $data)) {
            throw new Invalid_File_Exception(\sprintf('Failed to create temporary file at "%s".', $temp_path));
        }
        return new self($temp_path, true);
    }
    /**
     * @throws InvalidFileException when the file does not exist
     */
    public static function from_path(string $path): self
    {
        $path = self::normalize_path($path);
        if (!file_exists($path)) {
            throw new Invalid_File_Exception(\sprintf('File "%s" does not exist.', $path));
        }
        return new self($path, false);
    }
    private static function normalize_path(string $path): string
    {
        $path = trim($path);
        if (str_starts_with($path, '"') && str_ends_with($path, '"') || str_starts_with($path, "'") && str_ends_with($path, "'")) {
            $path = substr($path, 1, -1);
        }
        if (str_starts_with($path, 'file://')) {
            $path = urldecode(substr($path, 7));
            if ('\\' === \DIRECTORY_SEPARATOR && preg_match('#^/[a-zA-Z]:/#', $path)) {
                $path = substr($path, 1);
            }
        }
        // Remove backslash escapes (e.g., "\ " for escaped spaces) on non-Windows systems
        if ('\\' !== \DIRECTORY_SEPARATOR) {
            return preg_replace('/\\\\(.)/', '$1', $path) ?? $path;
        }
        return $path;
    }
    public function get_mime_type(): ?string
    {
        if (null !== $this->mime_type) {
            return $this->mime_type;
        }
        if (!$this->is_valid()) {
            return null;
        }
        if (class_exists(Mime_Types::class)) {
            return $this->mime_type = Mime_Types::get_default()->guess_mime_type($this->get_pathname());
        }
        $finfo = new \finfo(\FILEINFO_MIME_TYPE);
        return $this->mime_type = $finfo->file($this->get_pathname()) ?: null;
    }
    public function guess_extension(): ?string
    {
        $mime_type = $this->get_mime_type();
        if (null === $mime_type) {
            return null;
        }
        if (class_exists(Mime_Types::class)) {
            $extensions = Mime_Types::get_default()->get_extensions($mime_type);
            return $extensions[0] ?? null;
        }
        return match ($mime_type) {
            'image/png' => 'png',
            'image/jpeg' => 'jpg',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            'image/svg+xml' => 'svg',
            'application/pdf' => 'pdf',
            'text/plain' => 'txt',
            default => null,
        };
    }
    /**
     * @throws InvalidFileException when the file is invalid or the move/copy operation fails
     */
    public function move(string $directory, ?string $name = null): self
    {
        if (!$this->is_valid()) {
            throw new Invalid_File_Exception('Cannot move an invalid file.');
        }
        $name ??= $this->get_filename();
        $target = rtrim($directory, '/\\') . \DIRECTORY_SEPARATOR . $name;
        if (!is_dir($directory)) {
            if (false === @mkdir($directory, 0777, true) && !is_dir($directory)) {
                throw new Invalid_File_Exception(\sprintf('Unable to create the "%s" directory.', $directory));
            }
        } elseif (!is_writable($directory)) {
            throw new Invalid_File_Exception(\sprintf('Unable to write in the "%s" directory.', $directory));
        }
        if ($this->is_temp_file) {
            if (!@rename($this->get_pathname(), $target)) {
                throw new Invalid_File_Exception(\sprintf('Could not move the file "%s" to "%s".', $this->get_pathname(), $target));
            }
            unset(self::$temp_files[$this->get_pathname()]);
        } else if (!@copy($this->get_pathname(), $target)) {
            throw new Invalid_File_Exception(\sprintf('Could not copy the file "%s" to "%s".', $this->get_pathname(), $target));
        }
        @chmod($target, 0666 & ~umask());
        return new self($target, false, $this->mime_type);
    }
    public function cleanup(): void
    {
        if (!$this->is_temp_file) {
            return;
        }
        $path = $this->get_pathname();
        if (file_exists($path)) {
            @unlink($path);
        }
        unset(self::$temp_files[$path]);
    }
    /**
     * @internal
     */
    public static function cleanup_all(): void
    {
        foreach (self::$temp_files as $path) {
            if (file_exists($path)) {
                @unlink($path);
            }
        }
        self::$temp_files = [];
    }
    public function is_valid(): bool
    {
        return is_file($this->get_pathname()) && is_readable($this->get_pathname());
    }
    public function is_temp_file(): bool
    {
        return $this->is_temp_file;
    }
    /**
     * @throws InvalidFileException when the file is invalid or cannot be read
     */
    public function get_contents(): string
    {
        if (!$this->is_valid()) {
            throw new Invalid_File_Exception('Cannot read an invalid file.');
        }
        $contents = @file_get_contents($this->get_pathname());
        if (false === $contents) {
            throw new Invalid_File_Exception(\sprintf('Could not read file "%s".', $this->get_pathname()));
        }
        return $contents;
    }
    public function get_human_readable_size(): string
    {
        $size = $this->get_size();
        $units = ['B', 'KB', 'MB', 'GB'];
        $power = $size > 0 ? floor(log($size, 1024)) : 0;
        $power = min($power, \count($units) - 1);
        return \sprintf('%.1f %s', $size / 1024 ** $power, $units[$power]);
    }
}