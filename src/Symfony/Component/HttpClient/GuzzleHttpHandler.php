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

use Guzzle_Http\Exception\Connect_Exception;
use Guzzle_Http\Exception\Request_Exception;
use Guzzle_Http\Promise\Promise;
use Guzzle_Http\Promise\Promise_Interface;
use Guzzle_Http\Promise\Utils as PromiseUtils;
use Guzzle_Http\Psr7\Response as GuzzleResponse;
use Guzzle_Http\Psr7\Utils as Psr7Utils;
use Guzzle_Http\Transfer_Stats;
use Psr\Http\Message\Request_Interface;
use Psr\Http\Message\Response_Interface;
use Symfony\Contracts\Http_Client\Exception\Transport_Exception_Interface;
use Symfony\Contracts\Http_Client\Http_Client_Interface;
use Symfony\Contracts\Http_Client\Response_Interface as SymfonyResponseInterface;
/**
 * A Guzzle handler that uses Symfony's HttpClientInterface as its transport.
 *
 * This lets SDKs tightly coupled to Guzzle benefit from Symfony HttpClient's
 * features (e.g. retry logic, tracing, scoping, mocking) by plugging this
 * handler into a Guzzle client:
 *
 *   $handler = new GuzzleHttpHandler(HttpClient::create());
 *   $guzzle  = new \GuzzleHttp\Client(['handler' => $handler]);
 *
 * The handler is truly asynchronous: __invoke() returns a *pending* Promise
 * immediately without performing any I/O. The actual work is driven by
 * Symfony's HttpClientInterface::stream(), which multiplexes all in-flight
 * requests together - the same approach CurlMultiHandler takes with
 * curl_multi_*. Waiting on any single promise drives the whole pool so
 * concurrent requests benefit from parallelism automatically.
 *
 * Guzzle request options are mapped to their Symfony equivalents as faithfully
 * as possible; unsupported options are silently ignored so that existing SDK
 * option sets do not cause errors.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
final readonly class Guzzle_Http_Handler
{
    private Http_Client_Interface $client;
    /**
     * Maps each Symfony response (key) to a 3-tuple:
     *   [Psr7 RequestInterface, Guzzle options array, Guzzle Promise]
     *
     * @var \SplObjectStorage<SymfonyResponseInterface, array{0: RequestInterface, 1: array, 2: Promise}>
     */
    private \Spl_Object_Storage $pending;
    /**
     * PSR-7 response created eagerly on the first chunk so that the same
     * instance is passed to on_headers and later resolved by the promise.
     *
     * @var \SplObjectStorage<SymfonyResponseInterface, ResponseInterface>
     */
    private \Spl_Object_Storage $psr7Responses;
    public function __construct(?Http_Client_Interface $client = null, private bool $auto_upgrade_http_version = true)
    {
        $this->client = $client ?? Http_Client::create();
        $this->pending = new \Spl_Object_Storage();
        $this->psr7Responses = new \Spl_Object_Storage();
    }
    /**
     * Returns a *pending* Promise - no I/O is performed here.
     *
     * The wait function passed to the Promise drives Symfony's stream() loop,
     * which resolves all currently queued requests concurrently.
     */
    public function __invoke(Request_Interface $request, array $options): Promise_Interface
    {
        $symfony_options = $this->build_symfony_options($request, $options);
        try {
            $symfony_response = $this->client->request($request->get_method(), (string) $request->get_uri(), $symfony_options);
        } catch (\Exception $e) {
            // Option validation errors surface here synchronously.
            $p = new Promise();
            $p->reject($e);
            return $p;
        }
        $promise = new Promise(function () use ($symfony_response): void {
            $this->stream_pending(null, $symfony_response);
        }, function () use ($symfony_response): void {
            unset($this->pending[$symfony_response], $this->psr7Responses[$symfony_response]);
            $symfony_response->cancel();
        });
        $this->pending[$symfony_response] = [$request, $options, $promise];
        if (isset($options['delay'])) {
            $pause = $symfony_response->get_info('pause_handler');
            if (\is_callable($pause)) {
                $pause($options['delay'] / 1000.0);
            } else {
                usleep((int) ($options['delay'] * 1000));
            }
        }
        return $promise;
    }
    /**
     * Ticks the event loop: processes available I/O and runs queued tasks.
     *
     * @param float $timeout Maximum time in seconds to wait for network activity (0 = non-blocking)
     */
    public function tick(float $timeout = 1.0): void
    {
        $queue = Promise_Utils::queue();
        // Push streaming work onto the Guzzle task queue so that .then()
        // callbacks and other queued tasks get cooperative scheduling.
        $queue->add(fn() => $this->stream_pending($timeout, true));
        $queue->run();
    }
    /**
     * Runs until all outstanding connections have completed.
     */
    public function execute(): void
    {
        while ($this->pending->count()) {
            $this->stream_pending(null, false);
        }
    }
    /**
     * Performs one pass of streaming I/O over all pending responses.
     *
     * @param float|null $timeout Idle timeout passed to stream(); 0 for non-blocking, null for default
     */
    private function stream_pending(?float $timeout, bool|Symfony_Response_Interface $break_after): void
    {
        if (!$this->pending->count()) {
            return;
        }
        $queue = Promise_Utils::queue();
        $responses = [];
        foreach ($this->pending as $r) {
            $responses[] = $r;
        }
        foreach ($this->client->stream($responses, $timeout) as $response => $chunk) {
            try {
                if ($chunk->is_timeout()) {
                    continue;
                }
                if ($chunk->is_first()) {
                    // Deactivate 4xx/5xx exception throwing for this response;
                    // Guzzle's http_errors middleware handles that layer.
                    $response->get_status_code();
                    [, $guzzle_opts] = $this->pending[$response] ?? [null, []];
                    $sink = $guzzle_opts['sink'] ?? null;
                    $body = Psr7Utils::stream_for(\is_string($sink) ? fopen($sink, 'w+') : $sink ?? fopen('php://temp', 'r+'));
                    if (600 <= $response->get_status_code()) {
                        $psr_response = new Guzzle_Response(567, $response->get_headers(false), $body);
                        (new \ReflectionProperty($psr_response, 'statusCode'))->set_value($psr_response, $response->get_status_code());
                    } else {
                        $psr_response = new Guzzle_Response($response->get_status_code(), $response->get_headers(false), $body);
                    }
                    $this->psr7Responses[$response] = $psr_response;
                    if (isset($guzzle_opts['on_headers'])) {
                        try {
                            $guzzle_opts['on_headers']($psr_response);
                        } catch (\Throwable $e) {
                            [$guzzle_request, , $promise] = $this->pending[$response];
                            unset($this->pending[$response], $this->psr7Responses[$response]);
                            $this->fire_on_stats($guzzle_opts, $guzzle_request, $psr_response, $e, $response);
                            $promise->reject(new Request_Exception($e->get_message(), $guzzle_request, $psr_response, $e));
                            $response->cancel();
                        }
                    }
                }
                $content = $chunk->get_content();
                if ('' !== $content && isset($this->psr7Responses[$response])) {
                    $this->psr7Responses[$response]->get_body()->write($content);
                }
                if (!$chunk->is_last()) {
                    if (true === $break_after) {
                        break;
                    }
                    continue;
                }
                if (!isset($this->pending[$response])) {
                    unset($this->psr7Responses[$response]);
                } else {
                    $this->resolve_response($response);
                }
                if (\in_array($break_after, [true, $response], true)) {
                    break;
                }
            } catch (Transport_Exception_Interface $e) {
                if (isset($this->pending[$response])) {
                    $this->reject_response($response, $e);
                } else {
                    unset($this->psr7Responses[$response]);
                }
                if (\in_array($break_after, [true, $response], true)) {
                    break;
                }
            } finally {
                // Run .then() callbacks; they may add new entries to $this->pending.
                $queue->run();
            }
        }
    }
    private function resolve_response(Symfony_Response_Interface $response): void
    {
        [$guzzle_request, $options, $promise] = $this->pending[$response];
        $psr_response = $this->psr7Responses[$response];
        unset($this->pending[$response], $this->psr7Responses[$response]);
        $body = $psr_response->get_body();
        if ($body->is_seekable()) {
            try {
                $body->seek(0);
            } catch (\RuntimeException) {
                // ignore
            }
        }
        $this->fire_on_stats($options, $guzzle_request, $psr_response, null, $response);
        $promise->resolve($psr_response);
    }
    private function reject_response(Symfony_Response_Interface $response, Transport_Exception_Interface $e): void
    {
        [$guzzle_request, $options, $promise] = $this->pending[$response];
        $psr_response = $this->psr7Responses[$response] ?? null;
        unset($this->pending[$response], $this->psr7Responses[$response]);
        if ($body = $psr_response?->get_body()) {
            // Headers were already received: use RequestException so Guzzle middleware (e.g. retry)
            // can distinguish a mid-stream failure from a connection-level one.
            if ($body->is_seekable()) {
                try {
                    $body->seek(0);
                } catch (\RuntimeException) {
                    // ignore
                }
            }
            $this->fire_on_stats($options, $guzzle_request, $psr_response, $e, $response);
            $promise->reject(new Request_Exception($e->get_message(), $guzzle_request, $psr_response, $e));
        } else {
            // No headers received: connection-level failure.
            $this->fire_on_stats($options, $guzzle_request, null, $e, $response);
            $promise->reject(new Connect_Exception($e->get_message(), $guzzle_request, null, [], $e));
        }
    }
    private function fire_on_stats(array $options, Request_Interface $request, ?Response_Interface $psr_response, ?\Throwable $error, Symfony_Response_Interface $symfony_response): void
    {
        if (!isset($options['on_stats'])) {
            return;
        }
        $handler_stats = $symfony_response->get_info();
        $options['on_stats'](new Transfer_Stats($request, $psr_response, $handler_stats['total_time'] ?? 0.0, $error, $handler_stats));
    }
    private function build_symfony_options(Request_Interface $request, array $guzzle_options): array
    {
        $options = [];
        $options['headers'] = $this->extract_headers($request, $guzzle_options);
        $this->apply_body($request, $options);
        $this->apply_auth($guzzle_options, $options);
        $this->apply_timeouts($guzzle_options, $options);
        $this->apply_ssl($guzzle_options, $options);
        $this->apply_proxy($request, $guzzle_options, $options);
        $this->apply_redirects($guzzle_options, $options);
        $this->apply_misc($request, $guzzle_options, $options);
        $this->apply_decode_content($guzzle_options, $options);
        if (\extension_loaded('curl') && isset($guzzle_options['curl'])) {
            $this->apply_curl_options($guzzle_options['curl'], $options);
        }
        return $options;
    }
    /**
     * Merges headers from the PSR-7 request with any headers supplied via the
     * Guzzle 'headers' option (Guzzle option takes precedence).
     *
     * @return array<string, string[]>
     */
    private function extract_headers(Request_Interface $request, array $guzzle_options): array
    {
        $headers = $request->get_headers();
        foreach ($guzzle_options['headers'] ?? [] as $name => $value) {
            $headers[$name] = (array) $value;
        }
        return $headers;
    }
    private function apply_body(Request_Interface $request, array &$options): void
    {
        $key = 'content-length';
        $body = $request->get_body();
        if (!$size = $options['headers'][$key][0] ?? $options['headers'][$key = 'Content-Length'][0] ?? $body->get_size() ?? -1) {
            return;
        }
        if ($size < 0 || 1 << 21 < $size) {
            $options['body'] = static function (int $size) use ($body) {
                if ($body->is_seekable()) {
                    try {
                        $body->seek(0);
                    } catch (\RuntimeException) {
                        // ignore
                    }
                }
                while (!$body->eof()) {
                    yield $body->read($size);
                }
            };
        } else {
            if ($body->is_seekable()) {
                try {
                    $body->seek(0);
                } catch (\RuntimeException) {
                    // ignore
                }
            }
            $options['body'] = $body->get_contents();
        }
        if (0 < $size) {
            $options['headers'][$key] = [$size];
        }
    }
    /**
     * Maps Guzzle's 'auth' option.
     *
     * Supported forms:
     *   ['user', 'pass']          -> auth_basic
     *   ['user', 'pass', 'basic'] -> auth_basic
     *   ['token', '', 'bearer']   -> auth_bearer
     *   ['token', '', 'token']    -> auth_bearer (alias)
     */
    private function apply_auth(array $guzzle_options, array &$options): void
    {
        if (!isset($guzzle_options['auth'])) {
            return;
        }
        $auth = $guzzle_options['auth'];
        $type = strtolower($auth[2] ?? 'basic');
        if ('bearer' === $type || 'token' === $type) {
            $options['auth_bearer'] = $auth[0];
        } elseif ('ntlm' === $type) {
            array_pop($auth);
            $options['auth_ntlm'] = $auth;
        } else {
            $options['auth_basic'] = [$auth[0], $auth[1] ?? ''];
        }
    }
    private function apply_timeouts(array $guzzle_options, array &$options): void
    {
        if (0 < ($guzzle_options['timeout'] ?? 0)) {
            $options['max_duration'] = (float) $guzzle_options['timeout'];
        }
        if (0 < ($guzzle_options['read_timeout'] ?? 0)) {
            $options['timeout'] = (float) $guzzle_options['read_timeout'];
        }
        if (0 < ($guzzle_options['connect_timeout'] ?? 0)) {
            $options['max_connect_duration'] = (float) $guzzle_options['connect_timeout'];
        }
    }
    /**
     * Maps SSL/TLS related options.
     *
     * Guzzle 'verify' (bool|string)  -> Symfony verify_peer / verify_host / cafile / capath
     * Guzzle 'cert'   (string|array) -> Symfony local_cert [+ passphrase]
     * Guzzle 'ssl_key'(string|array) -> Symfony local_pk   [+ passphrase]
     * Guzzle 'crypto_method'         -> Symfony crypto_method (same PHP stream constants)
     */
    private function apply_ssl(array $guzzle_options, array &$options): void
    {
        if (isset($guzzle_options['verify'])) {
            if (false === $guzzle_options['verify']) {
                $options['verify_peer'] = false;
                $options['verify_host'] = false;
            } elseif (\is_string($guzzle_options['verify'])) {
                if (is_dir($guzzle_options['verify'])) {
                    $options['capath'] = $guzzle_options['verify'];
                } else {
                    $options['cafile'] = $guzzle_options['verify'];
                }
            }
        }
        if (isset($guzzle_options['cert'])) {
            $cert = $guzzle_options['cert'];
            if (\is_array($cert)) {
                [$cert_path, $cert_pass] = $cert;
                $options['local_cert'] = $cert_path;
                $options['passphrase'] = $cert_pass;
            } else {
                $options['local_cert'] = $cert;
            }
        }
        if (isset($guzzle_options['ssl_key'])) {
            $key = $guzzle_options['ssl_key'];
            if (\is_array($key)) {
                [$key_path, $key_pass] = $key;
                $options['local_pk'] = $key_path;
                // Do not clobber a passphrase already set by 'cert'.
                $options['passphrase'] ??= $key_pass;
            } else {
                $options['local_pk'] = $key;
            }
        }
        if (isset($guzzle_options['crypto_method'])) {
            $options['crypto_method'] = $guzzle_options['crypto_method'];
        }
    }
    /**
     * Maps Guzzle's 'proxy' option.
     *
     * String form -> proxy
     * Array form  -> selects proxy by URI scheme; 'no' key maps to no_proxy
     */
    private function apply_proxy(Request_Interface $request, array $guzzle_options, array &$options): void
    {
        if (!isset($guzzle_options['proxy'])) {
            return;
        }
        if (\is_string($proxy = $guzzle_options['proxy'])) {
            $options['proxy'] = $proxy;
            return;
        }
        $scheme = $request->get_uri()->get_scheme();
        if (isset($proxy[$scheme])) {
            $options['proxy'] = $proxy[$scheme];
        }
        if (isset($proxy['no'])) {
            $options['no_proxy'] = implode(',', (array) $proxy['no']);
        }
    }
    /**
     * Maps Guzzle's 'allow_redirects' to Symfony's 'max_redirects'.
     *
     * false             -> 0   (disable redirects)
     * true              -> (no override; Symfony defaults apply)
     * ['max' => N, ...] -> N
     */
    private function apply_redirects(array $guzzle_options, array &$options): void
    {
        if (!isset($guzzle_options['allow_redirects'])) {
            return;
        }
        if (!$ar = $guzzle_options['allow_redirects']) {
            $options['max_redirects'] = 0;
        } elseif (\is_array($ar)) {
            // 5 matches Guzzle's own default for the 'max' sub-key.
            $options['max_redirects'] = $ar['max'] ?? 5;
        }
    }
    /**
     * Miscellaneous options that do not fit a dedicated category.
     */
    private function apply_misc(Request_Interface $request, array $guzzle_options, array &$options): void
    {
        // We always drive I/O via stream(), so tell Symfony not to build its
        // own internal buffer - chunks are written directly to the PSR-7 response body stream.
        $options['buffer'] = false;
        if (!$this->auto_upgrade_http_version || '1.0' === $request->get_protocol_version()) {
            $options['http_version'] = $request->get_protocol_version();
        }
        // progress callback: (dlTotal, dlNow, ulTotal, ulNow) in Guzzle
        // on_progress:       (dlNow, dlTotal, info)           in Symfony
        if (isset($guzzle_options['progress'])) {
            $guzzle_progress = $guzzle_options['progress'];
            $options['on_progress'] = static function (int $dl_now, int $dl_size, array $info) use ($guzzle_progress): void {
                $guzzle_progress($dl_size, $dl_now, max(0, (int) ($info['upload_content_length'] ?? 0)), (int) ($info['size_upload'] ?? 0));
            };
        }
    }
    /**
     * Maps Guzzle's 'decode_content' option.
     *
     * true/string -> remove any explicit Accept-Encoding the caller set, so
     *                Symfony's HttpClient manages the header and auto-decodes
     * false       -> ensure an Accept-Encoding header is sent to disable
     *                Symfony's auto-decode behavior
     */
    private function apply_decode_content(array $guzzle_options, array &$options): void
    {
        if ($guzzle_options['decode_content'] ?? true) {
            unset($options['headers']['Accept-Encoding'], $options['headers']['accept-encoding']);
        } elseif (!isset($options['headers']['Accept-Encoding']) && !isset($options['headers']['accept-encoding'])) {
            $options['headers']['Accept-Encoding'] = ['identity'];
        }
    }
    /**
     * Maps raw cURL options from Guzzle's 'curl' option bag to Symfony options.
     *
     * Constants that have a direct named Symfony equivalent are translated;
     * everything else is forwarded verbatim via CurlHttpClient's 'extra.curl'
     * pass-through so that no option is silently dropped when the underlying
     * transport happens to be CurlHttpClient.
     *
     * Options managed internally by CurlHttpClient (or Symfony's other
     * mechanisms) are silently dropped to avoid the "Cannot set X with
     * extra.curl" exception that CurlHttpClient::validateExtraCurlOptions()
     * throws for those constants.
     */
    private function apply_curl_options(array $curl_options, array &$options): void
    {
        // Build a set of constants that CurlHttpClient rejects in extra.curl
        // together with options whose Symfony equivalents are already applied
        // via the PSR-7 request or other Guzzle option mappings.
        static $blocked;
        $blocked ??= array_flip(array_filter([
            // Auth - handled by applyAuth() / requires NTLM-specific logic.
            \CURLOPT_HTTPAUTH,
            \CURLOPT_USERPWD,
            // Body - set from the PSR-7 request body by applyBody().
            \CURLOPT_READDATA,
            \CURLOPT_READFUNCTION,
            \CURLOPT_INFILESIZE,
            \CURLOPT_POSTFIELDS,
            \CURLOPT_UPLOAD,
            // HTTP method - taken from the PSR-7 request.
            \CURLOPT_POST,
            \CURLOPT_PUT,
            \CURLOPT_CUSTOMREQUEST,
            \CURLOPT_HTTPGET,
            \CURLOPT_NOBODY,
            // Headers - merged by extractHeaders().
            \CURLOPT_HTTPHEADER,
            // Internal curl signal / redirect-type flags with no Symfony equiv.
            \CURLOPT_NOSIGNAL,
            \CURLOPT_POSTREDIR,
            // Progress - handled by applyMisc() via Guzzle's 'progress' option.
            \CURLOPT_NOPROGRESS,
            \CURLOPT_PROGRESSFUNCTION,
            // Blocked by CurlHttpClient::validateExtraCurlOptions().
            \CURLOPT_PRIVATE,
            \CURLOPT_HEADERFUNCTION,
            \CURLOPT_WRITEFUNCTION,
            \CURLOPT_VERBOSE,
            \CURLOPT_STDERR,
            \CURLOPT_RETURNTRANSFER,
            \CURLOPT_URL,
            \CURLOPT_FOLLOWLOCATION,
            \CURLOPT_HEADER,
            \CURLOPT_HTTP_VERSION,
            \CURLOPT_PORT,
            \CURLOPT_DNS_USE_GLOBAL_CACHE,
            \CURLOPT_PROTOCOLS,
            \CURLOPT_REDIR_PROTOCOLS,
            \CURLOPT_COOKIEFILE,
            \CURLINFO_REDIRECT_COUNT,
            \defined('CURLOPT_HTTP09_ALLOWED') ? \CURLOPT_HTTP09_ALLOWED : null,
            \defined('CURLOPT_HEADEROPT') ? \CURLOPT_HEADEROPT : null,
            // Pinned public key: curl uses "sha256//base64" which is
            // incompatible with Symfony's peer_fingerprint array format.
            \defined('CURLOPT_PINNEDPUBLICKEY') ? \CURLOPT_PINNEDPUBLICKEY : null,
        ]));
        foreach ($curl_options as $opt => $value) {
            if (isset($blocked[$opt])) {
                continue;
            }
            // CURLOPT_UNIX_SOCKET_PATH is conditionally defined; maps to bindto.
            if (\defined('CURLOPT_UNIX_SOCKET_PATH') && \CURLOPT_UNIX_SOCKET_PATH === $opt) {
                $options['bindto'] = $value;
                continue;
            }
            match ($opt) {
                \CURLOPT_CAINFO => $options['cafile'] = $value,
                \CURLOPT_CAPATH => $options['capath'] = $value,
                \CURLOPT_SSLCERT => $options['local_cert'] = $value,
                \CURLOPT_SSLKEY => $options['local_pk'] = $value,
                \CURLOPT_SSLCERTPASSWD, \CURLOPT_SSLKEYPASSWD => $options['passphrase'] = $value,
                \CURLOPT_SSL_CIPHER_LIST => $options['ciphers'] = $value,
                \CURLOPT_CERTINFO => $options['capture_peer_cert_chain'] = (bool) $value,
                \CURLOPT_PROXY => $options['proxy'] = $value,
                \CURLOPT_NOPROXY => $options['no_proxy'] = $value,
                \CURLOPT_USERAGENT => $options['headers']['User-Agent'] = [$value],
                \CURLOPT_REFERER => $options['headers']['Referer'] = [$value],
                \CURLOPT_INTERFACE => $options['bindto'] = $value,
                \CURLOPT_SSL_VERIFYPEER => $options['verify_peer'] = (bool) $value,
                \CURLOPT_SSL_VERIFYHOST => $options['verify_host'] = $value > 0,
                \CURLOPT_MAXREDIRS => $options['max_redirects'] = $value,
                \CURLOPT_TIMEOUT => $options['max_duration'] = (float) $value,
                \CURLOPT_TIMEOUT_MS => $options['max_duration'] = $value / 1000.0,
                \CURLOPT_CONNECTTIMEOUT => $options['max_connect_duration'] = (float) $value,
                \CURLOPT_CONNECTTIMEOUT_MS => $options['max_connect_duration'] = $value / 1000.0,
                default => $options['extra']['curl'][$opt] = $value,
            };
        }
    }
}