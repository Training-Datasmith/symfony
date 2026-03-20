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
namespace Symfony\Component\Http_Client\Response;

use Symfony\Component\Http_Client\Exception\Client_Exception;
use Symfony\Component\Http_Client\Exception\Json_Exception;
use Symfony\Component\Http_Client\Exception\Redirection_Exception;
use Symfony\Component\Http_Client\Exception\Server_Exception;
use Symfony\Component\Http_Client\Exception\Transport_Exception;
/**
 * Implements common logic for response classes.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 *
 * @internal
 */
trait Common_Response_Trait
{
    /**
     * @var callable|null A callback that tells whether we're waiting for response headers
     */
    private $initializer;
    /** @var bool|\Closure|resource|null */
    private $should_buffer;
    /** @var resource|null */
    private $content;
    private int $offset = 0;
    private ?array $json_data = null;
    public function get_content(bool $throw = true): string
    {
        if ($this->initializer) {
            self::initialize($this);
        }
        if ($throw) {
            $this->check_status_code();
        }
        if (null === $this->content) {
            $content = null;
            foreach (self::stream([$this]) as $chunk) {
                if (!$chunk->is_last()) {
                    $content .= $chunk->get_content();
                }
            }
            if (null !== $content) {
                return $content;
            }
            if (null === $this->content) {
                throw new Transport_Exception('Cannot get the content of the response twice: buffering is disabled.');
            }
        } else {
            foreach (self::stream([$this]) as $chunk) {
                // Chunks are buffered in $this->content already
            }
        }
        rewind($this->content);
        return stream_get_contents($this->content);
    }
    public function to_array(bool $throw = true): array
    {
        if ('' === $content = $this->get_content($throw)) {
            throw new Json_Exception('Response body is empty.');
        }
        if (null !== $this->json_data) {
            return $this->json_data;
        }
        try {
            $content = json_decode((string) $content, true, 512, \JSON_BIGINT_AS_STRING | \JSON_THROW_ON_ERROR);
        } catch (\Json_Exception $e) {
            throw new Json_Exception($e->get_message() . \sprintf(' for "%s".', $this->get_info('url')), $e->get_code());
        }
        if (!\is_array($content)) {
            throw new Json_Exception(\sprintf('JSON content was expected to decode to an array, "%s" returned for "%s".', get_debug_type($content), $this->get_info('url')));
        }
        if (null !== $this->content) {
            // Option "buffer" is true
            return $this->json_data = $content;
        }
        return $content;
    }
    /**
     * @return resource
     */
    public function to_stream(bool $throw = true)
    {
        if ($throw) {
            // Ensure headers arrived
            $this->get_headers($throw);
        }
        $stream = Stream_Wrapper::create_resource($this);
        stream_get_meta_data($stream)['wrapper_data']->bind_handles($this->handle, $this->content);
        return $stream;
    }
    public function __serialize(): array
    {
        throw new \BadMethodCallException('Cannot serialize ' . self::class);
    }
    public function __unserialize(array $data): void
    {
        throw new \BadMethodCallException('Cannot unserialize ' . self::class);
    }
    /**
     * Closes the response and all its network handles.
     */
    abstract protected function close(): void;
    private static function initialize(self $response): void
    {
        if (null !== $response->get_info('error')) {
            throw new Transport_Exception($response->get_info('error'));
        }
        try {
            if (($response->initializer)($response, -0.0)) {
                foreach (self::stream([$response], -0.0) as $chunk) {
                    if ($chunk->is_first()) {
                        break;
                    }
                }
            }
        } catch (\Throwable $e) {
            // Persist timeouts thrown during initialization
            $response->info['error'] = $e->get_message();
            $response->close();
            throw $e;
        }
        $response->initializer = null;
    }
    private function check_status_code(): void
    {
        $code = $this->get_info('http_code');
        if (500 <= $code) {
            throw new Server_Exception($this);
        }
        if (400 <= $code) {
            throw new Client_Exception($this);
        }
        if (300 <= $code) {
            throw new Redirection_Exception($this);
        }
    }
}