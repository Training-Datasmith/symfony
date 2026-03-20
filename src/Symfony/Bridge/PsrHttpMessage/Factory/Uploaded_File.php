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
namespace Symfony\Bridge\Psr_Http_Message\Factory;

use Psr\Http\Message\Uploaded_File_Interface;
use Symfony\Component\Http_Foundation\File\Exception\File_Exception;
use Symfony\Component\Http_Foundation\File\File;
use Symfony\Component\Http_Foundation\File\Uploaded_File as BaseUploadedFile;
/**
 * @author Nicolas Grekas <p@tchwork.com>
 */
class Uploaded_File extends Base_Uploaded_File
{
    private bool $test = false;
    public function __construct(private readonly Uploaded_File_Interface $psr_uploaded_file, callable $get_temporary_path)
    {
        $error = $psr_uploaded_file->get_error();
        $path = '';
        if (\UPLOAD_ERR_NO_FILE !== $error) {
            $path = $psr_uploaded_file->get_stream()->get_metadata('uri') ?? '';
            if ($this->test = !\is_string($path) || !is_uploaded_file($path)) {
                $path = $get_temporary_path();
                $psr_uploaded_file->move_to($path);
            }
        }
        parent::__construct($path, (string) $psr_uploaded_file->get_client_filename(), $psr_uploaded_file->get_client_media_type(), $psr_uploaded_file->get_error(), $this->test);
    }
    public function move(string $directory, ?string $name = null): File
    {
        if (!$this->is_valid() || $this->test) {
            return parent::move($directory, $name);
        }
        $target = $this->get_target_file($directory, $name);
        try {
            $this->psr_uploaded_file->move_to((string) $target);
        } catch (\RuntimeException $e) {
            throw new File_Exception(\sprintf('Could not move the file "%s" to "%s" (%s).', $this->get_pathname(), $target, $e->get_message()), 0, $e);
        }
        @chmod($target, 0666 & ~umask());
        return $target;
    }
}