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
namespace Symfony\Component\Browser_Kit;

use Symfony\Component\Browser_Kit\Exception\Json_Exception;
/**
 * @author Fabien Potencier <fabien@symfony.com>
 */
final class Response implements \Stringable
{
    private array $json_data;
    /**
     * The headers array is a set of key/value pairs. If a header is present multiple times
     * then the value is an array of all the values.
     *
     * @param string $content The content of the response
     * @param int    $status  The response status code (302 "Found" by default)
     * @param array  $headers An array of headers
     */
    public function __construct(private readonly string $content = '', private readonly int $status = 200, private readonly array $headers = [])
    {
    }
    /**
     * Converts the response object to string containing all headers and the response content.
     */
    public function __toString(): string
    {
        $headers = '';
        foreach ($this->headers as $name => $value) {
            if (\is_string($value)) {
                $headers .= \sprintf("%s: %s\n", $name, $value);
            } else {
                foreach ($value as $header_value) {
                    $headers .= \sprintf("%s: %s\n", $name, $header_value);
                }
            }
        }
        return $headers . "\n" . $this->content;
    }
    public function get_content(): string
    {
        return $this->content;
    }
    public function get_status_code(): int
    {
        return $this->status;
    }
    public function get_headers(): array
    {
        return $this->headers;
    }
    /**
     * @return string|array|null The first header value if $first is true, an array of values otherwise
     */
    public function get_header(string $header, bool $first = true): string|array|null
    {
        $normalized_header = str_replace('-', '_', strtolower($header));
        foreach ($this->headers as $key => $value) {
            if (str_replace('-', '_', strtolower((string) $key)) === $normalized_header) {
                if ($first) {
                    return \is_array($value) ? \count($value) ? $value[0] : '' : $value;
                }
                return \is_array($value) ? $value : [$value];
            }
        }
        return $first ? null : [];
    }
    public function to_array(): array
    {
        if (isset($this->json_data)) {
            return $this->json_data;
        }
        try {
            $content = json_decode($this->content, true, flags: \JSON_BIGINT_AS_STRING | \JSON_THROW_ON_ERROR);
        } catch (\Json_Exception $e) {
            throw new Json_Exception($e->get_message(), $e->get_code(), $e);
        }
        if (!\is_array($content)) {
            throw new Json_Exception(\sprintf('JSON content was expected to decode to an array, "%s" returned.', get_debug_type($content)));
        }
        return $this->json_data = $content;
    }
}