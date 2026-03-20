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
namespace Symfony\Component\Http_Foundation;

use Symfony\Component\Http_Foundation\File\Exception\File_Exception;
use Symfony\Component\Http_Foundation\File\File;
/**
 * BinaryFileResponse represents an HTTP response delivering a file.
 *
 * @author Niklas Fiekas <niklas.fiekas@tu-clausthal.de>
 * @author stealth35 <stealth35-php@live.fr>
 * @author Igor Wiedler <igor@wiedler.ch>
 * @author Jordan Alliot <jordan.alliot@gmail.com>
 * @author Sergey Linnik <linniksa@gmail.com>
 */
class Binary_File_Response extends Response
{
    protected static bool $trust_x_sendfile_type_header = false;
    protected File $file;
    protected ?\Spl_Temp_File_Object $temp_file_object = null;
    protected int $offset = 0;
    protected int $maxlen = -1;
    protected bool $delete_file_after_send = false;
    protected int $chunk_size = 16 * 1024;
    /**
     * @param \SplFileInfo|string $file               The file to stream
     * @param int                 $status             The response status code (200 "OK" by default)
     * @param array               $headers            An array of response headers
     * @param bool                $public             Files are public by default
     * @param string|null         $contentDisposition The type of Content-Disposition to set automatically with the filename
     * @param bool                $autoEtag           Whether the ETag header should be automatically set
     * @param bool                $autoLastModified   Whether the Last-Modified header should be automatically set
     */
    public function __construct(\Spl_File_Info|string $file, int $status = 200, array $headers = [], bool $public = true, ?string $content_disposition = null, bool $auto_etag = false, bool $auto_last_modified = true)
    {
        parent::__construct(null, $status, $headers);
        $this->set_file($file, $content_disposition, $auto_etag, $auto_last_modified);
        if ($public) {
            $this->set_public();
        }
    }
    /**
     * Sets the file to stream.
     *
     * @return $this
     *
     * @throws FileException
     */
    public function set_file(\Spl_File_Info|string $file, ?string $content_disposition = null, bool $auto_etag = false, bool $auto_last_modified = true): static
    {
        $is_temporary_file = $file instanceof \Spl_Temp_File_Object;
        $this->temp_file_object = $is_temporary_file ? $file : null;
        if (!$file instanceof File) {
            if ($file instanceof \Spl_File_Info) {
                $file = new File($file->get_pathname(), !$is_temporary_file);
            } else {
                $file = new File($file);
            }
        }
        if (!$file->is_readable() && !$is_temporary_file) {
            throw new File_Exception('File must be readable.');
        }
        $this->file = $file;
        if ($auto_etag) {
            $this->set_auto_etag();
        }
        if ($auto_last_modified && !$is_temporary_file) {
            $this->set_auto_last_modified();
        }
        if ($content_disposition) {
            $this->set_content_disposition($content_disposition);
        }
        return $this;
    }
    /**
     * Gets the file.
     */
    public function get_file(): File
    {
        return $this->file;
    }
    /**
     * Sets the response stream chunk size.
     *
     * @return $this
     */
    public function set_chunk_size(int $chunk_size): static
    {
        if ($chunk_size < 1) {
            throw new \InvalidArgumentException('The chunk size of a BinaryFileResponse cannot be less than 1.');
        }
        $this->chunk_size = $chunk_size;
        return $this;
    }
    /**
     * Automatically sets the Last-Modified header according the file modification date.
     *
     * @return $this
     */
    public function set_auto_last_modified(): static
    {
        $this->set_last_modified(\DateTimeImmutable::create_from_format('U', $this->temp_file_object ? time() : $this->file->get_m_time()));
        return $this;
    }
    /**
     * Automatically sets the ETag header according to the checksum of the file.
     *
     * @return $this
     */
    public function set_auto_etag(): static
    {
        $this->set_etag(base64_encode(hash_file('xxh128', $this->file->get_pathname(), true)));
        return $this;
    }
    /**
     * Sets the Content-Disposition header with the given filename.
     *
     * @param string $disposition      ResponseHeaderBag::DISPOSITION_INLINE or ResponseHeaderBag::DISPOSITION_ATTACHMENT
     * @param string $filename         Optionally use this UTF-8 encoded filename instead of the real name of the file
     * @param string $filenameFallback A fallback filename, containing only ASCII characters. Defaults to an automatically encoded filename
     *
     * @return $this
     */
    public function set_content_disposition(string $disposition, string $filename = '', string $filename_fallback = ''): static
    {
        if ('' === $filename) {
            $filename = $this->file->get_filename();
        }
        if ('' === $filename_fallback && (!preg_match('/^[\x20-\x7e]*$/', $filename) || str_contains($filename, '%'))) {
            $encoding = mb_detect_encoding($filename, null, true) ?: '8bit';
            for ($i = 0, $filename_length = mb_strlen($filename, $encoding); $i < $filename_length; ++$i) {
                $char = mb_substr($filename, $i, 1, $encoding);
                if ('%' === $char || \ord($char[0]) < 32 || \ord($char[0]) > 126) {
                    $filename_fallback .= '_';
                } else {
                    $filename_fallback .= $char;
                }
            }
        }
        $disposition_header = $this->headers->make_disposition($disposition, $filename, $filename_fallback);
        $this->headers->set('Content-Disposition', $disposition_header);
        return $this;
    }
    public function prepare(Request $request): static
    {
        if ($this->is_informational() || $this->is_empty()) {
            parent::prepare($request);
            $this->maxlen = 0;
            return $this;
        }
        if (!$this->headers->has('Content-Type')) {
            $mime_type = null;
            if (!$this->temp_file_object) {
                $mime_type = $this->file->get_mime_type();
            }
            $this->headers->set('Content-Type', $mime_type ?: 'application/octet-stream');
        }
        parent::prepare($request);
        $this->offset = 0;
        $this->maxlen = -1;
        if ($this->temp_file_object) {
            $file_size = $this->temp_file_object->fstat()['size'];
        } elseif (false === $file_size = $this->file->get_size()) {
            return $this;
        }
        $this->headers->remove('Transfer-Encoding');
        $this->headers->set('Content-Length', $file_size);
        if (!$this->headers->has('Accept-Ranges')) {
            // Only accept ranges on safe HTTP methods
            $this->headers->set('Accept-Ranges', $request->is_method_safe() ? 'bytes' : 'none');
        }
        if (self::$trust_x_sendfile_type_header && $request->headers->has('X-Sendfile-Type')) {
            // Use X-Sendfile, do not send any content.
            $type = $request->headers->get('X-Sendfile-Type');
            $path = $this->file->get_real_path();
            // Fall back to scheme://path for stream wrapped locations.
            if (false === $path) {
                $path = $this->file->get_pathname();
            }
            if ('x-accel-redirect' === strtolower((string) $type)) {
                // Do X-Accel-Mapping substitutions.
                // @link https://github.com/rack/rack/blob/main/lib/rack/sendfile.rb
                // @link https://mattbrictson.com/blog/accelerated-rails-downloads
                if (!$request->headers->has('X-Accel-Mapping')) {
                    throw new \LogicException('The "X-Accel-Mapping" header must be set when "X-Sendfile-Type" is set to "X-Accel-Redirect".');
                }
                $parts = Header_Utils::split($request->headers->get('X-Accel-Mapping'), ',=');
                foreach ($parts as $part) {
                    [$path_prefix, $location] = $part;
                    if (str_starts_with($path, (string) $path_prefix)) {
                        $path = $location . substr($path, \strlen((string) $path_prefix));
                        // Only set X-Accel-Redirect header if a valid URI can be produced
                        // as nginx does not serve arbitrary file paths.
                        $this->headers->set($type, rawurlencode($path));
                        $this->maxlen = 0;
                        break;
                    }
                }
            } else {
                $this->headers->set($type, $path);
                $this->maxlen = 0;
            }
        } elseif ($request->headers->has('Range') && $request->is_method('GET')) {
            // Process the range headers.
            if (!$request->headers->has('If-Range') || $this->has_valid_if_range_header($request->headers->get('If-Range'))) {
                $range = $request->headers->get('Range');
                if (str_starts_with((string) $range, 'bytes=')) {
                    [$start, $end] = explode('-', substr((string) $range, 6), 2) + [1 => 0];
                    $end = '' === $end ? $file_size - 1 : (int) $end;
                    if ('' === $start) {
                        $start = $file_size - $end;
                        $end = $file_size - 1;
                    } else {
                        $start = (int) $start;
                    }
                    if ($start <= $end) {
                        $end = min($end, $file_size - 1);
                        if ($start < 0 || $start > $end) {
                            $this->set_status_code(416);
                            $this->headers->set('Content-Range', \sprintf('bytes */%s', $file_size));
                        } else {
                            $this->maxlen = $end < $file_size ? $end - $start + 1 : -1;
                            $this->offset = $start;
                            $this->set_status_code(206);
                            $this->headers->set('Content-Range', \sprintf('bytes %s-%s/%s', $start, $end, $file_size));
                            $this->headers->set('Content-Length', $end - $start + 1);
                        }
                    }
                }
            }
        }
        if ($request->is_method('HEAD')) {
            $this->maxlen = 0;
        }
        return $this;
    }
    private function has_valid_if_range_header(?string $header): bool
    {
        if ($this->get_etag() === $header) {
            return true;
        }
        if (null === $last_modified = $this->get_last_modified()) {
            return false;
        }
        return $last_modified->format('D, d M Y H:i:s') . ' GMT' === $header;
    }
    public function send_content(): static
    {
        try {
            if (!$this->is_successful()) {
                return $this;
            }
            if (0 === $this->maxlen) {
                return $this;
            }
            $out = fopen('php://output', 'w');
            if ($this->temp_file_object) {
                $file = $this->temp_file_object;
                $file->rewind();
            } else {
                $file = new \Spl_File_Object($this->file->get_pathname(), 'r');
            }
            ignore_user_abort(true);
            if (0 !== $this->offset) {
                $file->fseek($this->offset);
            }
            $length = $this->maxlen;
            while ($length && !$file->eof()) {
                $read = $length > $this->chunk_size || 0 > $length ? $this->chunk_size : $length;
                if (false === $data = $file->fread($read)) {
                    break;
                }
                while ('' !== $data) {
                    $read = fwrite($out, $data);
                    if (false === $read || connection_aborted()) {
                        break 2;
                    }
                    if (0 < $length) {
                        $length -= $read;
                    }
                    $data = substr($data, $read);
                }
            }
            fclose($out);
        } finally {
            if (null === $this->temp_file_object && $this->delete_file_after_send && is_file($this->file->get_pathname())) {
                unlink($this->file->get_pathname());
            }
        }
        return $this;
    }
    /**
     * @throws \LogicException when the content is not null
     */
    public function set_content(?string $content): static
    {
        if (null !== $content) {
            throw new \LogicException('The content cannot be set on a BinaryFileResponse instance.');
        }
        return $this;
    }
    public function get_content(): string|false
    {
        return false;
    }
    /**
     * Trust X-Sendfile-Type header.
     */
    public static function trust_x_sendfile_type_header(): void
    {
        self::$trust_x_sendfile_type_header = true;
    }
    /**
     * If this is set to true, the file will be unlinked after the request is sent
     * Note: If the X-Sendfile header is used, the deleteFileAfterSend setting will not be used.
     *
     * @return $this
     */
    public function delete_file_after_send(bool $should_delete = true): static
    {
        $this->delete_file_after_send = $should_delete;
        return $this;
    }
    public function should_delete_file_after_send(): bool
    {
        return $this->delete_file_after_send;
    }
}