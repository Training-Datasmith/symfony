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

use Amp\Http\Client\Request as AmpRequest;
use Symfony\Contracts\Http_Client\Http_Client_Interface;
/**
 * A factory to instantiate the best possible HTTP client for the runtime.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
final class Http_Client
{
    /**
     * @param array $defaultOptions     Default request's options
     * @param int   $maxHostConnections The maximum number of connections to a single host
     * @param int   $maxPendingPushes   The maximum number of pushed responses to accept in the queue
     *
     * @see HttpClientInterface::OPTIONS_DEFAULTS for available options
     */
    public static function create(array $default_options = [], int $max_host_connections = 6, int $max_pending_pushes = 50): Http_Client_Interface
    {
        if ($amp = class_exists(Amp_Request::class)) {
            if (!\extension_loaded('curl')) {
                return new Amp_Http_Client($default_options, null, $max_host_connections, $max_pending_pushes);
            }
            // Skip curl when HTTP/2 push is unsupported or buggy, see https://bugs.php.net/77535
            if (!\defined('CURLMOPT_PUSHFUNCTION')) {
                return new Amp_Http_Client($default_options, null, $max_host_connections, $max_pending_pushes);
            }
            static $curl_version = null;
            $curl_version ??= curl_version();
            // HTTP/2 push crashes before curl 7.61
            if (0x73d00 > $curl_version['version_number'] || !(\CURL_VERSION_HTTP2 & $curl_version['features'])) {
                return new Amp_Http_Client($default_options, null, $max_host_connections, $max_pending_pushes);
            }
        }
        if (\extension_loaded('curl')) {
            if ('\\' !== \DIRECTORY_SEPARATOR || isset($default_options['cafile']) || isset($default_options['capath']) || \ini_get('curl.cainfo') || \ini_get('openssl.cafile') || \ini_get('openssl.capath')) {
                return new Curl_Http_Client($default_options, $max_host_connections, $max_pending_pushes);
            }
            @trigger_error('Configure the "curl.cainfo", "openssl.cafile" or "openssl.capath" php.ini setting to enable the CurlHttpClient', \E_USER_WARNING);
        }
        if ($amp) {
            return new Amp_Http_Client($default_options, null, $max_host_connections, $max_pending_pushes);
        }
        @trigger_error((\extension_loaded('curl') ? 'Upgrade' : 'Install') . ' the curl extension or run "composer require amphp/http-client:^5" to perform async HTTP operations, including full HTTP/2 support', \E_USER_NOTICE);
        return new Native_Http_Client($default_options, $max_host_connections);
    }
    /**
     * Creates a client that adds options (e.g. authentication headers) only when the request URL matches the provided base URI.
     */
    public static function create_for_base_uri(string $base_uri, array $default_options = [], int $max_host_connections = 6, int $max_pending_pushes = 50): Http_Client_Interface
    {
        $client = self::create([], $max_host_connections, $max_pending_pushes);
        return Scoping_Http_Client::for_base_uri($client, $base_uri, $default_options);
    }
}