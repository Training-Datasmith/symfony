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

use Psr\Http\Message\Response_Interface;
use Psr\Http\Message\Server_Request_Interface;
use Psr\Http\Message\Stream_Interface;
use Psr\Http\Message\Uploaded_File_Interface;
use Symfony\Bridge\Psr_Http_Message\Http_Foundation_Factory_Interface;
use Symfony\Component\Http_Foundation\Cookie;
use Symfony\Component\Http_Foundation\Request;
use Symfony\Component\Http_Foundation\Response;
use Symfony\Component\Http_Foundation\Streamed_Response;
/**
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class Http_Foundation_Factory implements Http_Foundation_Factory_Interface
{
    /**
     * @param int $responseBufferMaxLength The maximum output buffering size for each iteration when sending the response
     */
    public function __construct(private readonly int $response_buffer_max_length = 16372)
    {
    }
    public function create_request(Server_Request_Interface $psr_request, bool $streamed = false): Request
    {
        $server = [];
        $uri = $psr_request->get_uri();
        $server['SERVER_NAME'] = $uri->get_host();
        $server['SERVER_PORT'] = $uri->get_port() ?: ('https' === $uri->get_scheme() ? 443 : 80);
        $server['REQUEST_URI'] = $uri->get_path();
        $server['QUERY_STRING'] = $uri->get_query();
        if ('' !== $server['QUERY_STRING']) {
            $server['REQUEST_URI'] .= '?' . $server['QUERY_STRING'];
        }
        if ('https' === $uri->get_scheme()) {
            $server['HTTPS'] = 'on';
        }
        $server['REQUEST_METHOD'] = $psr_request->get_method();
        $server = array_replace($psr_request->get_server_params(), $server);
        $parsed_body = $psr_request->get_parsed_body();
        $parsed_body = \is_array($parsed_body) ? $parsed_body : [];
        $request = new Request($psr_request->get_query_params(), $parsed_body, $psr_request->get_attributes(), $psr_request->get_cookie_params(), $this->get_files($psr_request->get_uploaded_files()), $server, $streamed ? $psr_request->get_body()->detach() : $psr_request->get_body()->__toString());
        $request->headers->add($psr_request->get_headers());
        return $request;
    }
    /**
     * Converts to the input array to $_FILES structure.
     */
    private function get_files(array $uploaded_files): array
    {
        $files = [];
        foreach ($uploaded_files as $key => $value) {
            if ($value instanceof Uploaded_File_Interface) {
                $files[$key] = $this->create_uploaded_file($value);
            } else {
                $files[$key] = $this->get_files($value);
            }
        }
        return $files;
    }
    /**
     * Creates Symfony UploadedFile instance from PSR-7 ones.
     */
    private function create_uploaded_file(Uploaded_File_Interface $psr_uploaded_file): Uploaded_File
    {
        return new Uploaded_File($psr_uploaded_file, fn(): string => $this->get_temporary_path());
    }
    /**
     * Gets a temporary file path.
     */
    protected function get_temporary_path(): string
    {
        return tempnam(sys_get_temp_dir(), 'symfony');
    }
    public function create_response(Response_Interface $psr_response, bool $streamed = false): Response
    {
        $cookies = $psr_response->get_header('Set-Cookie');
        $psr_response = $psr_response->without_header('Set-Cookie');
        if ($streamed) {
            $response = new Streamed_Response($this->create_streamed_response_callback($psr_response->get_body()), $psr_response->get_status_code(), $psr_response->get_headers());
        } else {
            $response = new Response($psr_response->get_body()->__toString(), $psr_response->get_status_code(), $psr_response->get_headers());
        }
        $response->set_protocol_version($psr_response->get_protocol_version());
        foreach ($cookies as $cookie) {
            $response->headers->set_cookie(Cookie::from_string($cookie));
        }
        return $response;
    }
    private function create_streamed_response_callback(Stream_Interface $body): callable
    {
        return function () use ($body): void {
            if ($body->is_seekable()) {
                $body->rewind();
            }
            if (!$body->is_readable()) {
                echo $body;
                return;
            }
            while (!$body->eof()) {
                echo $body->read($this->response_buffer_max_length);
            }
        };
    }
}