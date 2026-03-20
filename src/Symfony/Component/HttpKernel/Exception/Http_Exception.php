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
namespace Symfony\Component\Http_Kernel\Exception;

/**
 * HttpException.
 *
 * @author Kris Wallsmith <kris@symfony.com>
 */
class Http_Exception extends \RuntimeException implements Http_Exception_Interface
{
    public function __construct(private readonly int $status_code, string $message = '', ?\Throwable $previous = null, private array $headers = [], int $code = 0)
    {
        parent::__construct($message, $code, $previous);
    }
    public static function from_status_code(int $status_code, string $message = '', ?\Throwable $previous = null, array $headers = [], int $code = 0): self
    {
        return match ($status_code) {
            400 => new Bad_Request_Http_Exception($message, $previous, $code, $headers),
            403 => new Access_Denied_Http_Exception($message, $previous, $code, $headers),
            404 => new Not_Found_Http_Exception($message, $previous, $code, $headers),
            406 => new Not_Acceptable_Http_Exception($message, $previous, $code, $headers),
            409 => new Conflict_Http_Exception($message, $previous, $code, $headers),
            410 => new Gone_Http_Exception($message, $previous, $code, $headers),
            411 => new Length_Required_Http_Exception($message, $previous, $code, $headers),
            412 => new Precondition_Failed_Http_Exception($message, $previous, $code, $headers),
            423 => new Locked_Http_Exception($message, $previous, $code, $headers),
            415 => new Unsupported_Media_Type_Http_Exception($message, $previous, $code, $headers),
            422 => new Unprocessable_Entity_Http_Exception($message, $previous, $code, $headers),
            428 => new Precondition_Required_Http_Exception($message, $previous, $code, $headers),
            429 => new Too_Many_Requests_Http_Exception(null, $message, $previous, $code, $headers),
            503 => new Service_Unavailable_Http_Exception(null, $message, $previous, $code, $headers),
            default => new static($status_code, $message, $previous, $headers, $code),
        };
    }
    public function get_status_code(): int
    {
        return $this->status_code;
    }
    public function get_headers(): array
    {
        return $this->headers;
    }
    public function set_headers(array $headers): void
    {
        $this->headers = $headers;
    }
}