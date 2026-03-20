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

/**
 * @author Fabien Potencier <fabien@symfony.com>
 */
class Request
{
    /**
     * @param string      $uri        The request URI
     * @param string      $method     The HTTP method request
     * @param array       $parameters The request parameters
     * @param array       $files      An array of uploaded files
     * @param array       $cookies    An array of cookies
     * @param array       $server     An array of server parameters
     * @param string|null $content    The raw body data
     */
    public function __construct(protected string $uri, protected string $method, protected array $parameters = [], protected array $files = [], protected array $cookies = [], protected array $server = [], protected ?string $content = null)
    {
        array_walk_recursive($parameters, static function (&$value): void {
            $value = (string) $value;
        });
        $this->parameters = $parameters;
    }
    /**
     * Gets the request URI.
     */
    public function get_uri(): string
    {
        return $this->uri;
    }
    /**
     * Gets the request HTTP method.
     */
    public function get_method(): string
    {
        return $this->method;
    }
    /**
     * Gets the request parameters.
     */
    public function get_parameters(): array
    {
        return $this->parameters;
    }
    /**
     * Gets the request server files.
     */
    public function get_files(): array
    {
        return $this->files;
    }
    /**
     * Gets the request cookies.
     */
    public function get_cookies(): array
    {
        return $this->cookies;
    }
    /**
     * Gets the request server parameters.
     */
    public function get_server(): array
    {
        return $this->server;
    }
    /**
     * Gets the request raw body data.
     */
    public function get_content(): ?string
    {
        return $this->content;
    }
}