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

use Symfony\Component\Browser_Kit\Exception\LogicException;
use Symfony\Component\Http_Client\Http_Client;
use Symfony\Component\Mime\Part\Abstract_Part;
use Symfony\Component\Mime\Part\Data_Part;
use Symfony\Component\Mime\Part\Multipart\Form_Data_Part;
use Symfony\Component\Mime\Part\Text_Part;
use Symfony\Contracts\Http_Client\Http_Client_Interface;
/**
 * An implementation of a browser using the HttpClient component
 * to make real HTTP requests.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @template-extends AbstractBrowser<Request, Response>
 */
class Http_Browser extends Abstract_Browser
{
    private readonly Http_Client_Interface $client;
    public function __construct(?Http_Client_Interface $client = null, ?History $history = null, ?Cookie_Jar $cookie_jar = null)
    {
        if (!$client && !class_exists(Http_Client::class)) {
            throw new LogicException(\sprintf('You cannot use "%s" as the HttpClient component is not installed. Try running "composer require symfony/http-client".', self::class));
        }
        $this->client = $client ?? Http_Client::create();
        parent::__construct([], $history, $cookie_jar);
    }
    /**
     * @param Request $request
     */
    protected function do_request(object $request): Response
    {
        $headers = $this->get_headers($request);
        [$body, $extra_headers] = $this->get_body_and_extra_headers($request, $headers);
        $response = $this->client->request($request->get_method(), $request->get_uri(), ['headers' => array_merge($headers, $extra_headers), 'body' => $body, 'max_redirects' => 0]);
        return new Response($response->get_content(false), $response->get_status_code(), $response->get_headers(false));
    }
    /**
     * @return array [$body, $headers]
     */
    private function get_body_and_extra_headers(Request $request, array $headers): array
    {
        if (\in_array($request->get_method(), ['GET', 'HEAD'], true) && !isset($headers['content-type'])) {
            return ['', []];
        }
        if (!class_exists(Abstract_Part::class)) {
            throw new LogicException('You cannot pass non-empty bodies as the Mime component is not installed. Try running "composer require symfony/mime".');
        }
        if (null !== $content = $request->get_content()) {
            if (isset($headers['content-type'])) {
                return [$content, []];
            }
            $part = new Text_Part($content, 'utf-8', 'plain', '8bit');
            return [$part->body_to_string(), $part->get_prepared_headers()->to_array()];
        }
        $fields = $request->get_parameters();
        if ($uploaded_files = $this->get_uploaded_files($request->get_files())) {
            $part = new Form_Data_Part(array_replace_recursive($fields, $uploaded_files));
            return [$part->body_to_iterable(), $part->get_prepared_headers()->to_array()];
        }
        if (!$fields) {
            return ['', []];
        }
        array_walk_recursive($fields, $caster = static function (&$v) use (&$caster): void {
            if (\is_object($v)) {
                if ($vars = get_object_vars($v)) {
                    array_walk_recursive($vars, $caster);
                    $v = $vars;
                } elseif ($v instanceof \Stringable) {
                    $v = (string) $v;
                }
            }
        });
        return [http_build_query($fields, '', '&'), ['Content-Type' => 'application/x-www-form-urlencoded']];
    }
    protected function get_headers(Request $request): array
    {
        $headers = [];
        foreach ($request->get_server() as $key => $value) {
            $key = strtolower(str_replace('_', '-', $key));
            $content_headers = ['content-length' => true, 'content-md5' => true, 'content-type' => true];
            if (str_starts_with($key, 'http-')) {
                $headers[substr($key, 5)] = $value;
            } elseif (isset($content_headers[$key])) {
                // CONTENT_* are not prefixed with HTTP_
                $headers[$key] = $value;
            }
        }
        $cookies = [];
        foreach ($this->get_cookie_jar()->all_raw_values($request->get_uri()) as $name => $value) {
            $cookies[] = $name . '=' . $value;
        }
        if ($cookies) {
            $headers['cookie'] = implode('; ', $cookies);
        }
        return $headers;
    }
    /**
     * Recursively go through the list. If the file has a tmp_name, convert it to a DataPart.
     * Keep the original hierarchy.
     */
    private function get_uploaded_files(array $files): array
    {
        $uploaded_files = [];
        foreach ($files as $name => $file) {
            if (!\is_array($file)) {
                return $uploaded_files;
            }
            if (!isset($file['tmp_name'])) {
                $uploaded_files[$name] = $this->get_uploaded_files($file);
                continue;
            }
            if ('' === $file['tmp_name']) {
                $uploaded_files[$name] = new Data_Part('', '');
                continue;
            }
            $uploaded_files[$name] = Data_Part::from_path($file['tmp_name'], $file['name']);
        }
        return $uploaded_files;
    }
}