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
namespace Symfony\Component\Http_Client;

use Symfony\Contracts\Http_Client\Http_Client_Interface;
/**
 * A helper providing autocompletion for available options.
 *
 * @see HttpClientInterface for a description of each options.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
class Http_Options
{
    private array $options = [];
    public function to_array(): array
    {
        return $this->options;
    }
    /**
     * @return $this
     */
    public function set_auth_basic(
        string $user,
        #[\Sensitive_Parameter]
        string $password = ''
    ): static
    {
        $this->options['auth_basic'] = $user;
        if ('' !== $password) {
            $this->options['auth_basic'] .= ':' . $password;
        }
        return $this;
    }
    /**
     * @return $this
     */
    public function set_auth_bearer(
        #[\Sensitive_Parameter]
        string $token
    ): static
    {
        $this->options['auth_bearer'] = $token;
        return $this;
    }
    /**
     * @return $this
     */
    public function set_query(array $query): static
    {
        $this->options['query'] = $query;
        return $this;
    }
    /**
     * @return $this
     */
    public function set_header(string $key, string $value): static
    {
        $this->options['headers'][$key] = $value;
        return $this;
    }
    /**
     * @return $this
     */
    public function set_headers(iterable $headers): static
    {
        $this->options['headers'] = $headers;
        return $this;
    }
    /**
     * @param array|string|resource|\Traversable|\Closure $body
     *
     * @return $this
     */
    public function set_body(mixed $body): static
    {
        $this->options['body'] = $body;
        return $this;
    }
    /**
     * @return $this
     */
    public function set_json(mixed $json): static
    {
        $this->options['json'] = $json;
        return $this;
    }
    /**
     * @return $this
     */
    public function set_user_data(mixed $data): static
    {
        $this->options['user_data'] = $data;
        return $this;
    }
    /**
     * @return $this
     */
    public function set_max_redirects(int $max): static
    {
        $this->options['max_redirects'] = $max;
        return $this;
    }
    /**
     * @return $this
     */
    public function set_http_version(string $version): static
    {
        $this->options['http_version'] = $version;
        return $this;
    }
    /**
     * @return $this
     */
    public function set_base_uri(string $uri): static
    {
        $this->options['base_uri'] = $uri;
        return $this;
    }
    /**
     * @return $this
     */
    public function set_vars(array $vars): static
    {
        $this->options['vars'] = $vars;
        return $this;
    }
    /**
     * @return $this
     */
    public function buffer(bool $buffer): static
    {
        $this->options['buffer'] = $buffer;
        return $this;
    }
    /**
     * @param callable(int, int, array, \Closure|null=):void $callback
     *
     * @return $this
     */
    public function set_on_progress(callable $callback): static
    {
        $this->options['on_progress'] = $callback;
        return $this;
    }
    /**
     * @return $this
     */
    public function resolve(array $host_ips): static
    {
        $this->options['resolve'] = $host_ips;
        return $this;
    }
    /**
     * @return $this
     */
    public function set_proxy(string $proxy): static
    {
        $this->options['proxy'] = $proxy;
        return $this;
    }
    /**
     * @return $this
     */
    public function set_no_proxy(string $no_proxy): static
    {
        $this->options['no_proxy'] = $no_proxy;
        return $this;
    }
    /**
     * @return $this
     */
    public function set_timeout(float $timeout): static
    {
        $this->options['timeout'] = $timeout;
        return $this;
    }
    /**
     * @return $this
     */
    public function set_max_duration(float $max_duration): static
    {
        $this->options['max_duration'] = $max_duration;
        return $this;
    }
    /**
     * @return $this
     */
    public function set_max_connect_duration(float $max_connect_duration): static
    {
        $this->options['max_connect_duration'] = $max_connect_duration;
        return $this;
    }
    /**
     * @return $this
     */
    public function bind_to(string $bindto): static
    {
        $this->options['bindto'] = $bindto;
        return $this;
    }
    /**
     * @return $this
     */
    public function verify_peer(bool $verify): static
    {
        $this->options['verify_peer'] = $verify;
        return $this;
    }
    /**
     * @return $this
     */
    public function verify_host(bool $verify): static
    {
        $this->options['verify_host'] = $verify;
        return $this;
    }
    /**
     * @return $this
     */
    public function set_ca_file(string $cafile): static
    {
        $this->options['cafile'] = $cafile;
        return $this;
    }
    /**
     * @return $this
     */
    public function set_ca_path(string $capath): static
    {
        $this->options['capath'] = $capath;
        return $this;
    }
    /**
     * @return $this
     */
    public function set_local_cert(string $cert): static
    {
        $this->options['local_cert'] = $cert;
        return $this;
    }
    /**
     * @return $this
     */
    public function set_local_pk(string $pk): static
    {
        $this->options['local_pk'] = $pk;
        return $this;
    }
    /**
     * @return $this
     */
    public function set_passphrase(string $passphrase): static
    {
        $this->options['passphrase'] = $passphrase;
        return $this;
    }
    /**
     * @return $this
     */
    public function set_ciphers(string $ciphers): static
    {
        $this->options['ciphers'] = $ciphers;
        return $this;
    }
    /**
     * @return $this
     */
    public function set_peer_fingerprint(string|array $fingerprint): static
    {
        $this->options['peer_fingerprint'] = $fingerprint;
        return $this;
    }
    /**
     * @return $this
     */
    public function capture_peer_cert_chain(bool $capture): static
    {
        $this->options['capture_peer_cert_chain'] = $capture;
        return $this;
    }
    /**
     * @return $this
     */
    public function set_extra(string $name, mixed $value): static
    {
        $this->options['extra'][$name] = $value;
        return $this;
    }
}