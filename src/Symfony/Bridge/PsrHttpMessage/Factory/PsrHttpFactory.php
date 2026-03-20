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

use Http\Discovery\Psr17Factory as DiscoveryPsr17Factory;
use Nyholm\Psr7\Factory\Psr17Factory as NyholmPsr17Factory;
use Psr\Http\Message\Response_Factory_Interface;
use Psr\Http\Message\Response_Interface;
use Psr\Http\Message\Server_Request_Factory_Interface;
use Psr\Http\Message\Server_Request_Interface;
use Psr\Http\Message\Stream_Factory_Interface;
use Psr\Http\Message\Uploaded_File_Factory_Interface;
use Psr\Http\Message\Uploaded_File_Interface;
use Symfony\Bridge\Psr_Http_Message\Http_Message_Factory_Interface;
use Symfony\Component\Http_Foundation\Binary_File_Response;
use Symfony\Component\Http_Foundation\File\Uploaded_File;
use Symfony\Component\Http_Foundation\Request;
use Symfony\Component\Http_Foundation\Response;
use Symfony\Component\Http_Foundation\Streamed_Response;
/**
 * Builds Psr\HttpMessage instances using a PSR-17 implementation.
 *
 * @author Antonio J. García Lagar <aj@garcialagar.es>
 * @author Aurélien Pillevesse <aurelienpillevesse@hotmail.fr>
 */
class Psr_Http_Factory implements Http_Message_Factory_Interface
{
    private readonly Server_Request_Factory_Interface $server_request_factory;
    private readonly Stream_Factory_Interface $stream_factory;
    private readonly Uploaded_File_Factory_Interface $uploaded_file_factory;
    private readonly Response_Factory_Interface $response_factory;
    public function __construct(?Server_Request_Factory_Interface $server_request_factory = null, ?Stream_Factory_Interface $stream_factory = null, ?Uploaded_File_Factory_Interface $uploaded_file_factory = null, ?Response_Factory_Interface $response_factory = null)
    {
        if (null === $server_request_factory || null === $stream_factory || null === $uploaded_file_factory || null === $response_factory) {
            $psr17Factory = match (true) {
                class_exists(Discovery_Psr17factory::class) => new Discovery_Psr17factory(),
                class_exists(Nyholm_Psr17factory::class) => new Nyholm_Psr17factory(),
                default => throw new \LogicException(\sprintf('You cannot use the "%s" as no PSR-17 factories have been provided. Try running "composer require php-http/discovery psr/http-factory-implementation:*".', self::class)),
            };
            $server_request_factory ??= $psr17Factory;
            $stream_factory ??= $psr17Factory;
            $uploaded_file_factory ??= $psr17Factory;
            $response_factory ??= $psr17Factory;
        }
        $this->server_request_factory = $server_request_factory;
        $this->stream_factory = $stream_factory;
        $this->uploaded_file_factory = $uploaded_file_factory;
        $this->response_factory = $response_factory;
    }
    public function create_request(Request $symfony_request): Server_Request_Interface
    {
        $uri = $symfony_request->server->get('QUERY_STRING', '');
        $uri = $symfony_request->get_scheme_and_http_host() . $symfony_request->get_base_url() . $symfony_request->get_path_info() . ('' !== $uri ? '?' . $uri : '');
        $request = $this->server_request_factory->create_server_request($symfony_request->get_method(), $uri, $symfony_request->server->all());
        foreach ($symfony_request->headers->all() as $name => $value) {
            try {
                $request = $request->with_header($name, $value);
            } catch (\InvalidArgumentException) {
                // ignore invalid header
            }
        }
        $body = $this->stream_factory->create_stream_from_resource($symfony_request->get_content(true));
        $format = $symfony_request->get_content_type_format();
        if ('json' === $format) {
            $parsed_body = json_decode($symfony_request->get_content(), true, 512, \JSON_BIGINT_AS_STRING);
            if (!\is_array($parsed_body)) {
                $parsed_body = null;
            }
        } else {
            $parsed_body = $symfony_request->request->all();
        }
        $request = $request->with_body($body)->with_uploaded_files($this->get_files($symfony_request->files->all()))->with_cookie_params($symfony_request->cookies->all())->with_query_params($symfony_request->query->all())->with_parsed_body($parsed_body);
        foreach ($symfony_request->attributes->all() as $key => $value) {
            $request = $request->with_attribute($key, $value);
        }
        return $request;
    }
    /**
     * Converts Symfony uploaded files array to the PSR one.
     */
    private function get_files(array $uploaded_files): array
    {
        $files = [];
        foreach ($uploaded_files as $key => $value) {
            if (null === $value) {
                $files[$key] = $this->uploaded_file_factory->create_uploaded_file($this->stream_factory->create_stream(), 0, \UPLOAD_ERR_NO_FILE);
                continue;
            }
            if ($value instanceof Uploaded_File) {
                $files[$key] = $this->create_uploaded_file($value);
            } else {
                $files[$key] = $this->get_files($value);
            }
        }
        return $files;
    }
    /**
     * Creates a PSR-7 UploadedFile instance from a Symfony one.
     */
    private function create_uploaded_file(Uploaded_File $symfony_uploaded_file): Uploaded_File_Interface
    {
        return $this->uploaded_file_factory->create_uploaded_file($this->stream_factory->create_stream_from_file($symfony_uploaded_file->get_real_path()), (int) $symfony_uploaded_file->get_size(), $symfony_uploaded_file->get_error(), $symfony_uploaded_file->get_client_original_name(), $symfony_uploaded_file->get_client_mime_type());
    }
    public function create_response(Response $symfony_response): Response_Interface
    {
        $response = $this->response_factory->create_response($symfony_response->get_status_code(), Response::$status_texts[$symfony_response->get_status_code()] ?? '');
        if ($symfony_response instanceof Binary_File_Response && !$symfony_response->headers->has('Content-Range')) {
            $stream = $this->stream_factory->create_stream_from_file($symfony_response->get_file()->get_pathname());
        } else {
            $stream = $this->stream_factory->create_stream_from_file('php://temp', 'wb+');
            if ($symfony_response instanceof Streamed_Response || $symfony_response instanceof Binary_File_Response) {
                ob_start(static function ($buffer) use ($stream): string {
                    $stream->write($buffer);
                    return '';
                }, 1);
                try {
                    $symfony_response->send_content();
                } finally {
                    ob_end_clean();
                }
            } else {
                $stream->write($symfony_response->get_content());
            }
        }
        $response = $response->with_body($stream);
        $headers = $symfony_response->headers->all();
        $cookies = $symfony_response->headers->get_cookies();
        if ($cookies) {
            $headers['Set-Cookie'] = [];
            foreach ($cookies as $cookie) {
                $headers['Set-Cookie'][] = $cookie->__toString();
            }
        }
        foreach ($headers as $name => $value) {
            try {
                $response = $response->with_header($name, $value);
            } catch (\InvalidArgumentException) {
                // ignore invalid header
            }
        }
        $protocol_version = $symfony_response->get_protocol_version();
        return $response->with_protocol_version($protocol_version);
    }
}