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

use Symfony\Component\Http_Foundation\File\Exception\Cannot_Write_File_Exception;
use Symfony\Component\Http_Foundation\File\Exception\Extension_File_Exception;
use Symfony\Component\Http_Foundation\File\Exception\File_Exception;
use Symfony\Component\Http_Foundation\File\Exception\File_Not_Found_Exception;
use Symfony\Component\Http_Foundation\File\Exception\Form_Size_File_Exception;
use Symfony\Component\Http_Foundation\File\Exception\Ini_Size_File_Exception;
use Symfony\Component\Http_Foundation\File\Exception\No_File_Exception;
use Symfony\Component\Http_Foundation\File\Exception\No_Tmp_Dir_File_Exception;
use Symfony\Component\Http_Foundation\File\Exception\Partial_File_Exception;
use Symfony\Component\Mime\Mime_Types;
/**
 * A file uploaded through a form.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 * @author Florian Eckerstorfer <florian@eckerstorfer.org>
 * @author Fabien Potencier <fabien@symfony.com>
 */
class Uploaded_File extends File
{
    private readonly string $original_name;
    private readonly string $mime_type;
    private readonly int $error;
    private readonly string $original_path;
    /**
     * Accepts the information of the uploaded file as provided by the PHP global $_FILES.
     *
     * The file object is only created when the uploaded file is valid (i.e. when the
     * isValid() method returns true). Otherwise the only methods that could be called
     * on an UploadedFile instance are:
     *
     *   * getClientOriginalName,
     *   * getClientMimeType,
     *   * isValid,
     *   * getError.
     *
     * Calling any other method on an non-valid instance will cause an unpredictable result.
     *
     * @param string      $path         The full temporary path to the file
     * @param string      $originalName The original file name of the uploaded file
     * @param string|null $mimeType     The type of the file as provided by PHP; null defaults to application/octet-stream
     * @param int|null    $error        The error constant of the upload (one of PHP's UPLOAD_ERR_XXX constants); null defaults to UPLOAD_ERR_OK
     * @param bool        $test         Whether the test mode is active
     *                                  Local files are used in test mode hence the code should not enforce HTTP uploads
     *
     * @throws FileException         If file_uploads is disabled
     * @throws FileNotFoundException If the file does not exist
     */
    public function __construct(string $path, string $original_name, ?string $mime_type = null, ?int $error = null, private readonly bool $test = false)
    {
        $this->original_name = $this->get_name($original_name);
        $this->original_path = strtr($original_name, '\\', '/');
        $this->mime_type = $mime_type ?: 'application/octet-stream';
        $this->error = $error ?: \UPLOAD_ERR_OK;
        parent::__construct($path, \UPLOAD_ERR_OK === $this->error);
    }
    /**
     * Returns the original file name.
     *
     * It is extracted from the request from which the file has been uploaded.
     * This should not be considered as a safe value to use for a file name on your servers.
     */
    public function get_client_original_name(): string
    {
        return $this->original_name;
    }
    /**
     * Returns the original file extension.
     *
     * It is extracted from the original file name that was uploaded.
     * This should not be considered as a safe value to use for a file name on your servers.
     */
    public function get_client_original_extension(): string
    {
        return pathinfo($this->original_name, \PATHINFO_EXTENSION);
    }
    /**
     * Returns the original file full path.
     *
     * It is extracted from the request from which the file has been uploaded.
     * This should not be considered as a safe value to use for a file name/path on your servers.
     *
     * If this file was uploaded with the "webkitdirectory" upload directive, this will contain
     * the path of the file relative to the uploaded root directory. Otherwise this will be identical
     * to getClientOriginalName().
     */
    public function get_client_original_path(): string
    {
        return $this->original_path;
    }
    /**
     * Returns the file mime type.
     *
     * The client mime type is extracted from the request from which the file
     * was uploaded, so it should not be considered as a safe value.
     *
     * For a trusted mime type, use getMimeType() instead (which guesses the mime
     * type based on the file content).
     *
     * @see getMimeType()
     */
    public function get_client_mime_type(): string
    {
        return $this->mime_type;
    }
    /**
     * Returns the extension based on the client mime type.
     *
     * If the mime type is unknown, returns null.
     *
     * This method uses the mime type as guessed by getClientMimeType()
     * to guess the file extension. As such, the extension returned
     * by this method cannot be trusted.
     *
     * For a trusted extension, use guessExtension() instead (which guesses
     * the extension based on the guessed mime type for the file).
     *
     * @see guessExtension()
     * @see getClientMimeType()
     */
    public function guess_client_extension(): ?string
    {
        if (!class_exists(Mime_Types::class)) {
            throw new \LogicException('You cannot guess the extension as the Mime component is not installed. Try running "composer require symfony/mime".');
        }
        return Mime_Types::get_default()->get_extensions($this->get_client_mime_type())[0] ?? null;
    }
    /**
     * Returns the upload error.
     *
     * If the upload was successful, the constant UPLOAD_ERR_OK is returned.
     * Otherwise one of the other UPLOAD_ERR_XXX constants is returned.
     */
    public function get_error(): int
    {
        return $this->error;
    }
    /**
     * Returns whether the file has been uploaded with HTTP and no error occurred.
     */
    public function is_valid(): bool
    {
        $is_ok = \UPLOAD_ERR_OK === $this->error;
        return $this->test ? $is_ok : $is_ok && is_uploaded_file($this->get_pathname());
    }
    /**
     * Moves the file to a new location.
     *
     * @throws FileException if, for any reason, the file could not have been moved
     */
    public function move(string $directory, ?string $name = null): File
    {
        if ($this->is_valid()) {
            if ($this->test) {
                return parent::move($directory, $name);
            }
            $target = $this->get_target_file($directory, $name);
            set_error_handler(static function ($type, $msg) use (&$error): void {
                $error = $msg;
            });
            try {
                $moved = move_uploaded_file($this->get_pathname(), $target);
            } finally {
                restore_error_handler();
            }
            if (!$moved) {
                throw new File_Exception(\sprintf('Could not move the file "%s" to "%s" (%s).', $this->get_pathname(), $target, strip_tags((string) $error)));
            }
            @chmod($target, 0666 & ~umask());
            return $target;
        }
        switch ($this->error) {
            case \UPLOAD_ERR_INI_SIZE:
                throw new Ini_Size_File_Exception($this->get_exception_message());
            case \UPLOAD_ERR_FORM_SIZE:
                throw new Form_Size_File_Exception($this->get_exception_message());
            case \UPLOAD_ERR_PARTIAL:
                throw new Partial_File_Exception($this->get_exception_message());
            case \UPLOAD_ERR_NO_FILE:
                throw new No_File_Exception($this->get_exception_message());
            case \UPLOAD_ERR_CANT_WRITE:
                throw new Cannot_Write_File_Exception($this->get_exception_message());
            case \UPLOAD_ERR_NO_TMP_DIR:
                throw new No_Tmp_Dir_File_Exception($this->get_exception_message());
            case \UPLOAD_ERR_EXTENSION:
                throw new Extension_File_Exception($this->get_exception_message());
        }
        throw new File_Exception($this->get_exception_message());
    }
    /**
     * Retrieves a user-friendly error message for file upload issues, if any.
     */
    public function get_error_message(): string
    {
        return \UPLOAD_ERR_OK !== $this->error ? $this->get_exception_message() : '';
    }
    /**
     * Returns the maximum size of an uploaded file as configured in php.ini.
     *
     * @return int|float The maximum size of an uploaded file in bytes (returns float if size > PHP_INT_MAX)
     */
    public static function get_max_filesize(): int|float
    {
        $size_post_max = self::parse_filesize(\ini_get('post_max_size'));
        $size_upload_max = self::parse_filesize(\ini_get('upload_max_filesize'));
        return min($size_post_max ?: \PHP_INT_MAX, $size_upload_max ?: \PHP_INT_MAX);
    }
    private static function parse_filesize(string $size): int
    {
        if ('' === $size) {
            return 0;
        }
        $size = strtolower($size);
        $max = ltrim($size, '+');
        if (str_starts_with($max, '0x')) {
            $max = \intval($max, 16);
        } elseif (str_starts_with($max, '0')) {
            $max = \intval($max, 8);
        } else {
            $max = (int) $max;
        }
        switch (substr($size, -1)) {
            case 't':
                $max *= 1024;
            // no break
            case 'g':
                $max *= 1024;
            // no break
            case 'm':
                $max *= 1024;
            // no break
            case 'k':
                $max *= 1024;
        }
        return $max;
    }
    /**
     * Returns an informative upload error message.
     */
    private function get_exception_message(): string
    {
        static $errors = [\UPLOAD_ERR_INI_SIZE => 'The file "%s" exceeds your upload_max_filesize ini directive (limit is %d KiB).', \UPLOAD_ERR_FORM_SIZE => 'The file "%s" exceeds the upload limit defined in your form.', \UPLOAD_ERR_PARTIAL => 'The file "%s" was only partially uploaded.', \UPLOAD_ERR_NO_FILE => 'No file was uploaded.', \UPLOAD_ERR_CANT_WRITE => 'The file "%s" could not be written on disk.', \UPLOAD_ERR_NO_TMP_DIR => 'File could not be uploaded: missing temporary directory.', \UPLOAD_ERR_EXTENSION => 'File upload was stopped by a PHP extension.'];
        $error_code = $this->error;
        $max_filesize = \UPLOAD_ERR_INI_SIZE === $error_code ? self::get_max_filesize() / 1024 : 0;
        $message = $errors[$error_code] ?? 'The file "%s" was not uploaded due to an unknown error.';
        return \sprintf($message, $this->get_client_original_name(), $max_filesize);
    }
}