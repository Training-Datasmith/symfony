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
namespace Symfony\Component\Http_Foundation;

use Symfony\Component\Http_Foundation\Exception\Bad_Request_Exception;
use Symfony\Component\Http_Foundation\Exception\Conflicting_Headers_Exception;
use Symfony\Component\Http_Foundation\Exception\Json_Exception;
use Symfony\Component\Http_Foundation\Exception\Session_Not_Found_Exception;
use Symfony\Component\Http_Foundation\Exception\Suspicious_Operation_Exception;
use Symfony\Component\Http_Foundation\Session\Session_Interface;
// Help opcache.preload discover always-needed symbols
class_exists(Accept_Header::class);
class_exists(File_Bag::class);
class_exists(Header_Bag::class);
class_exists(Header_Utils::class);
class_exists(Input_Bag::class);
class_exists(Parameter_Bag::class);
class_exists(Server_Bag::class);
/**
 * Request represents an HTTP request.
 *
 * The methods dealing with URL accept / return a raw path (% encoded):
 *   * getBasePath
 *   * getBaseUrl
 *   * getPathInfo
 *   * getRequestUri
 *   * getUri
 *   * getUriForPath
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class Request implements \Stringable
{
    public const HEADER_FORWARDED = 0b1;
    // When using RFC 7239
    public const HEADER_X_FORWARDED_FOR = 0b10;
    public const HEADER_X_FORWARDED_HOST = 0b100;
    public const HEADER_X_FORWARDED_PROTO = 0b1000;
    public const HEADER_X_FORWARDED_PORT = 0b10000;
    public const HEADER_X_FORWARDED_PREFIX = 0b100000;
    public const HEADER_X_FORWARDED_AWS_ELB = 0b11010;
    // AWS ELB doesn't send X-Forwarded-Host
    public const HEADER_X_FORWARDED_TRAEFIK = 0b111110;
    // All "X-Forwarded-*" headers sent by Traefik reverse proxy
    public const METHOD_HEAD = 'HEAD';
    public const METHOD_GET = 'GET';
    public const METHOD_POST = 'POST';
    public const METHOD_PUT = 'PUT';
    public const METHOD_PATCH = 'PATCH';
    public const METHOD_DELETE = 'DELETE';
    public const METHOD_PURGE = 'PURGE';
    public const METHOD_OPTIONS = 'OPTIONS';
    public const METHOD_TRACE = 'TRACE';
    public const METHOD_CONNECT = 'CONNECT';
    public const METHOD_QUERY = 'QUERY';
    private const FORWARDED_PARAMS = [self::HEADER_X_FORWARDED_FOR => 'for', self::HEADER_X_FORWARDED_HOST => 'host', self::HEADER_X_FORWARDED_PROTO => 'proto', self::HEADER_X_FORWARDED_PORT => 'host'];
    /**
     * Names for headers that can be trusted when
     * using trusted proxies.
     *
     * The FORWARDED header is the standard as of rfc7239.
     *
     * The other headers are non-standard, but widely used
     * by popular reverse proxies (like Apache mod_proxy or Amazon EC2).
     */
    private const TRUSTED_HEADERS = [self::HEADER_FORWARDED => 'FORWARDED', self::HEADER_X_FORWARDED_FOR => 'X_FORWARDED_FOR', self::HEADER_X_FORWARDED_HOST => 'X_FORWARDED_HOST', self::HEADER_X_FORWARDED_PROTO => 'X_FORWARDED_PROTO', self::HEADER_X_FORWARDED_PORT => 'X_FORWARDED_PORT', self::HEADER_X_FORWARDED_PREFIX => 'X_FORWARDED_PREFIX'];
    /**
     * This mapping is used when no exact MIME match is found in $formats.
     *
     * It enables mappings like application/soap+xml -> xml.
     *
     * @see https://datatracker.ietf.org/doc/html/rfc6839
     * @see https://datatracker.ietf.org/doc/html/rfc7303
     * @see https://www.iana.org/assignments/media-types/media-types.xhtml
     */
    private const STRUCTURED_SUFFIX_FORMATS = ['json' => 'json', 'xml' => 'xml', 'xhtml' => 'html', 'cbor' => 'cbor', 'zip' => 'zip', 'ber' => 'asn1', 'der' => 'asn1', 'tlv' => 'tlv', 'wbxml' => 'xml', 'yaml' => 'yaml'];
    /**
     * Custom parameters.
     */
    public Parameter_Bag $attributes {
        set {
            trigger_deprecation('symfony/http-foundation', '8.1', 'Directly setting property "attributes" of "%s" is deprecated; pass attributes as a constructor argument or call "initialize()" instead.', self::class);
            $this->attributes = $value;
        }
    }
    /**
     * Request body parameters ($_POST).
     *
     * @see getPayload() for portability between content types
     */
    public Input_Bag $request {
        set {
            trigger_deprecation('symfony/http-foundation', '8.1', 'Directly setting property "request" of "%s" is deprecated; pass the POST data as a constructor argument or call "initialize()" instead.', self::class);
            $this->request = $value;
        }
    }
    /**
     * Query string parameters ($_GET).
     *
     * @var InputBag<string>
     */
    public Input_Bag $query {
        set {
            trigger_deprecation('symfony/http-foundation', '8.1', 'Directly setting property "query" of "%s" is deprecated; pass query parameters as a constructor argument or call "initialize()" instead.', self::class);
            $this->query = $value;
        }
    }
    /**
     * Server and execution environment parameters ($_SERVER).
     */
    public Server_Bag $server {
        set {
            trigger_deprecation('symfony/http-foundation', '8.1', 'Directly setting property "server" of "%s" is deprecated; pass server parameters as a constructor argument or call "initialize()" instead.', self::class);
            $this->server = $value;
        }
    }
    /**
     * Uploaded files ($_FILES).
     */
    public File_Bag $files {
        set {
            trigger_deprecation('symfony/http-foundation', '8.1', 'Directly setting property "files" of "%s" is deprecated; pass files as a constructor argument or call "initialize()" instead.', self::class);
            $this->files = $value;
        }
    }
    /**
     * Cookies ($_COOKIE).
     *
     * @var InputBag<string>
     */
    public Input_Bag $cookies {
        set {
            trigger_deprecation('symfony/http-foundation', '8.1', 'Directly setting property "cookies" of "%s" is deprecated; pass cookies as a constructor argument or call "initialize()" instead.', self::class);
            $this->cookies = $value;
        }
    }
    /**
     * Headers (taken from the $_SERVER).
     */
    public Header_Bag $headers {
        set {
            trigger_deprecation('symfony/http-foundation', '8.1', 'Directly setting property "headers" of "%s" is deprecated; pass header parameters as a constructor argument or call "initialize()" instead.', self::class);
            $this->headers = $value;
        }
    }
    /**
     * @var string|resource|false|null
     */
    protected $content;
    /**
     * @var string[]|null
     */
    protected ?array $languages = null;
    /**
     * @var string[]|null
     */
    protected ?array $charsets = null;
    /**
     * @var string[]|null
     */
    protected ?array $encodings = null;
    /**
     * @var string[]|null
     */
    protected ?array $acceptable_content_types = null;
    protected ?string $path_info = null;
    protected ?string $request_uri = null;
    protected ?string $base_url = null;
    protected ?string $base_path = null;
    protected ?string $method = null;
    protected ?string $format = null;
    protected Session_Interface|\Closure|null $session = null;
    protected ?string $locale = null;
    protected string $default_locale = 'en';
    /**
     * @var array<string, string[]>|null
     */
    protected static ?array $formats = null;
    /**
     * @var string[]
     */
    protected static array $trusted_proxies = [];
    /**
     * @var string[]
     */
    protected static array $trusted_host_patterns = [];
    /**
     * @var string[]
     */
    protected static array $trusted_hosts = [];
    protected static bool $http_method_parameter_override = false;
    /**
     * The HTTP methods that can be overridden.
     *
     * @var uppercase-string[]|null
     */
    protected static ?array $allowed_http_method_override = null;
    protected static ?\Closure $request_factory = null;
    private ?string $preferred_format = null;
    private bool $is_host_valid = true;
    private bool $is_forwarded_valid = true;
    private bool $is_safe_content_preferred;
    private array $trusted_values_cache = [];
    private static int $trusted_header_set = -1;
    private bool $is_iis_rewrite = false;
    /**
     * @param array                $query      The GET parameters
     * @param array                $request    The POST parameters
     * @param array                $attributes The request attributes (parameters parsed from the PATH_INFO, ...)
     * @param array                $cookies    The COOKIE parameters
     * @param array                $files      The FILES parameters
     * @param array                $server     The SERVER parameters
     * @param string|resource|null $content    The raw body data
     */
    public function __construct(array $query = [], array $request = [], array $attributes = [], array $cookies = [], array $files = [], array $server = [], $content = null)
    {
        $this->initialize($query, $request, $attributes, $cookies, $files, $server, $content);
    }
    /**
     * Sets the parameters for this request.
     *
     * This method also re-initializes all properties.
     *
     * @param array                $query      The GET parameters
     * @param array                $request    The POST parameters
     * @param array                $attributes The request attributes (parameters parsed from the PATH_INFO, ...)
     * @param array                $cookies    The COOKIE parameters
     * @param array                $files      The FILES parameters
     * @param array                $server     The SERVER parameters
     * @param string|resource|null $content    The raw body data
     */
    public function initialize(array $query = [], array $request = [], array $attributes = [], array $cookies = [], array $files = [], array $server = [], $content = null): void
    {
        self::set_property($this, 'request', new Input_Bag($request));
        self::set_property($this, 'query', new Input_Bag($query));
        self::set_property($this, 'attributes', new Parameter_Bag($attributes));
        self::set_property($this, 'cookies', new Input_Bag($cookies));
        self::set_property($this, 'files', new File_Bag($files));
        self::set_property($this, 'server', new Server_Bag($server));
        self::set_property($this, 'headers', new Header_Bag($this->server->get_headers()));
        $this->content = $content;
        $this->languages = null;
        $this->charsets = null;
        $this->encodings = null;
        $this->acceptable_content_types = null;
        $this->path_info = null;
        $this->request_uri = null;
        $this->base_url = null;
        $this->base_path = null;
        $this->method = null;
        $this->format = null;
    }
    /**
     * Creates a new request with values from PHP's super globals.
     */
    public static function create_from_globals(): static
    {
        if (!\in_array($_SERVER['REQUEST_METHOD'] ?? null, ['PUT', 'DELETE', 'PATCH', 'QUERY'], true)) {
            return self::create_request_from_factory($_GET, $_POST, [], $_COOKIE, $_FILES, $_SERVER);
        }
        try {
            [$post, $files] = request_parse_body();
        } catch (\Request_Parse_Body_Exception) {
            $post = $_POST;
            $files = $_FILES;
        }
        return self::create_request_from_factory($_GET, $post, [], $_COOKIE, $files, $_SERVER);
    }
    /**
     * Creates a Request based on a given URI and configuration.
     *
     * The information contained in the URI always take precedence
     * over the other information (server and parameters).
     *
     * @param string               $uri        The URI
     * @param string               $method     The HTTP method
     * @param array                $parameters The query (GET) or request (POST) parameters
     * @param array                $cookies    The request cookies ($_COOKIE)
     * @param array                $files      The request files ($_FILES)
     * @param array                $server     The server parameters ($_SERVER)
     * @param string|resource|null $content    The raw body data
     *
     * @throws BadRequestException When the URI is invalid
     */
    public static function create(string $uri, string $method = 'GET', array $parameters = [], array $cookies = [], array $files = [], array $server = [], $content = null): static
    {
        $server = array_replace(['SERVER_NAME' => 'localhost', 'SERVER_PORT' => 80, 'HTTP_HOST' => 'localhost', 'HTTP_USER_AGENT' => 'Symfony', 'HTTP_ACCEPT' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8', 'HTTP_ACCEPT_LANGUAGE' => 'en-us,en;q=0.5', 'HTTP_ACCEPT_CHARSET' => 'ISO-8859-1,utf-8;q=0.7,*;q=0.7', 'REMOTE_ADDR' => '127.0.0.1', 'SCRIPT_NAME' => '', 'SCRIPT_FILENAME' => '', 'SERVER_PROTOCOL' => 'HTTP/1.1', 'REQUEST_TIME' => time(), 'REQUEST_TIME_FLOAT' => microtime(true)], $server);
        $server['PATH_INFO'] = '';
        $server['REQUEST_METHOD'] = strtoupper($method);
        if (($i = strcspn($uri, ':/?#')) && ':' === ($uri[$i] ?? null) && (strspn($uri, 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789+-.') !== $i || strcspn($uri, 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ'))) {
            throw new Bad_Request_Exception('Invalid URI: Scheme is malformed.');
        }
        if (false === $components = parse_url(\strlen($uri) !== strcspn($uri, '?#') ? $uri : $uri . '#')) {
            throw new Bad_Request_Exception('Invalid URI.');
        }
        $part = ($components['user'] ?? '') . ':' . ($components['pass'] ?? '');
        if (':' !== $part && \strlen($part) !== strcspn($part, '[]')) {
            throw new Bad_Request_Exception('Invalid URI: Userinfo is malformed.');
        }
        if (($part = $components['host'] ?? '') && !self::is_host_valid($part)) {
            throw new Bad_Request_Exception('Invalid URI: Host is malformed.');
        }
        if (false !== ($i = strpos($uri, '\\')) && $i < strcspn($uri, '?#')) {
            throw new Bad_Request_Exception('Invalid URI: A URI cannot contain a backslash.');
        }
        if (\strlen($uri) !== strcspn($uri, "\r\n\t")) {
            throw new Bad_Request_Exception('Invalid URI: A URI cannot contain CR/LF/TAB characters.');
        }
        if ('' !== $uri && (\ord($uri[0]) <= 32 || \ord($uri[-1]) <= 32)) {
            throw new Bad_Request_Exception('Invalid URI: A URI must not start nor end with ASCII control characters or spaces.');
        }
        if (isset($components['host'])) {
            $server['SERVER_NAME'] = $components['host'];
            $server['HTTP_HOST'] = $components['host'];
        }
        if (isset($components['scheme'])) {
            if ('https' === $components['scheme']) {
                $server['HTTPS'] = 'on';
                $server['SERVER_PORT'] = 443;
            } else {
                unset($server['HTTPS']);
                $server['SERVER_PORT'] = 80;
            }
        }
        if (isset($components['port'])) {
            $server['SERVER_PORT'] = $components['port'];
            $server['HTTP_HOST'] .= ':' . $components['port'];
        }
        if (isset($components['user'])) {
            $server['PHP_AUTH_USER'] = $components['user'];
        }
        if (isset($components['pass'])) {
            $server['PHP_AUTH_PW'] = $components['pass'];
        }
        if ('' === $path = $components['path'] ?? '') {
            $components['path'] = '/';
        } elseif (!isset($components['scheme']) && !isset($components['host']) && '/' !== $path[0]) {
            if (false !== $pos = strpos($path, '/')) {
                $path = substr($path, 0, $pos);
            }
            if (str_contains($path, ':')) {
                throw new Bad_Request_Exception('Invalid URI: Path is malformed.');
            }
        }
        switch (strtoupper($method)) {
            case 'POST':
            case 'PUT':
            case 'DELETE':
            case 'QUERY':
                if (!isset($server['CONTENT_TYPE'])) {
                    $server['CONTENT_TYPE'] = 'application/x-www-form-urlencoded';
                }
            // no break
            case 'PATCH':
                $request = $parameters;
                $query = [];
                break;
            default:
                $request = [];
                $query = $parameters;
                break;
        }
        $query_string = '';
        if (isset($components['query'])) {
            parse_str(html_entity_decode($components['query']), $qs);
            if ($query) {
                $query = array_replace($qs, $query);
                $query_string = http_build_query($query, '', '&');
            } else {
                $query = $qs;
                $query_string = $components['query'];
            }
        } elseif ($query) {
            $query_string = http_build_query($query, '', '&');
        }
        $server['REQUEST_URI'] = $components['path'] . ('' !== $query_string ? '?' . $query_string : '');
        $server['QUERY_STRING'] = $query_string;
        return self::create_request_from_factory($query, $request, [], $cookies, $files, $server, $content);
    }
    /**
     * Sets a callable able to create a Request instance.
     *
     * This is mainly useful when you need to override the Request class
     * to keep BC with an existing system. It should not be used for any
     * other purpose.
     */
    public static function set_factory(?callable $callable): void
    {
        self::$request_factory = null === $callable ? null : $callable(...);
    }
    /**
     * Clones a request and overrides some of its parameters.
     *
     * @param array|null $query      The GET parameters
     * @param array|null $request    The POST parameters
     * @param array|null $attributes The request attributes (parameters parsed from the PATH_INFO, ...)
     * @param array|null $cookies    The COOKIE parameters
     * @param array|null $files      The FILES parameters
     * @param array|null $server     The SERVER parameters
     */
    public function duplicate(?array $query = null, ?array $request = null, ?array $attributes = null, ?array $cookies = null, ?array $files = null, ?array $server = null): static
    {
        $dup = clone $this;
        if (null !== $query) {
            self::set_property($dup, 'query', new Input_Bag($query));
        }
        if (null !== $request) {
            self::set_property($dup, 'request', new Input_Bag($request));
        }
        if (null !== $attributes) {
            self::set_property($dup, 'attributes', new Parameter_Bag($attributes));
        }
        if (null !== $cookies) {
            self::set_property($dup, 'cookies', new Input_Bag($cookies));
        }
        if (null !== $files) {
            self::set_property($dup, 'files', new File_Bag($files));
        }
        if (null !== $server) {
            self::set_property($dup, 'server', new Server_Bag($server));
            self::set_property($dup, 'headers', new Header_Bag($dup->server->get_headers()));
        }
        $dup->languages = null;
        $dup->charsets = null;
        $dup->encodings = null;
        $dup->acceptable_content_types = null;
        $dup->path_info = null;
        $dup->request_uri = null;
        $dup->base_url = null;
        $dup->base_path = null;
        $dup->method = null;
        $dup->format = null;
        if (!$dup->attributes->has('_format') && $this->attributes->has('_format')) {
            $dup->attributes->set('_format', $this->attributes->get('_format'));
        }
        if (!$dup->get_request_format(null)) {
            $dup->set_request_format($this->get_request_format(null));
        }
        return $dup;
    }
    /**
     * Clones the current request.
     *
     * Note that the session is not cloned as duplicated requests
     * are most of the time sub-requests of the main one.
     */
    public function __clone()
    {
        self::set_property($this, 'query', clone $this->query);
        self::set_property($this, 'request', clone $this->request);
        self::set_property($this, 'attributes', clone $this->attributes);
        self::set_property($this, 'cookies', clone $this->cookies);
        self::set_property($this, 'files', clone $this->files);
        self::set_property($this, 'server', clone $this->server);
        self::set_property($this, 'headers', clone $this->headers);
    }
    public function __toString(): string
    {
        $content = $this->get_content();
        $cookie_header = '';
        $cookies = [];
        foreach ($this->cookies as $k => $v) {
            $cookies[] = \is_array($v) ? http_build_query([$k => $v], '', '; ', \PHP_QUERY_RFC3986) : "{$k}={$v}";
        }
        if ($cookies) {
            $cookie_header = 'Cookie: ' . implode('; ', $cookies) . "\r\n";
        }
        return \sprintf('%s %s %s', $this->get_method(), $this->get_request_uri(), $this->server->get('SERVER_PROTOCOL')) . "\r\n" . $this->headers . $cookie_header . "\r\n" . $content;
    }
    /**
     * Overrides the PHP global variables according to this request instance.
     *
     * It overrides $_GET, $_POST, $_REQUEST, $_SERVER, $_COOKIE.
     * $_FILES is never overridden, see rfc1867
     */
    public function override_globals(): void
    {
        $this->server->set('QUERY_STRING', static::normalize_query_string(http_build_query($this->query->all(), '', '&')));
        $_GET = $this->query->all();
        $_POST = $this->request->all();
        $_SERVER = $this->server->all();
        $_COOKIE = $this->cookies->all();
        foreach ($this->headers->all() as $key => $value) {
            $key = strtoupper(str_replace('-', '_', $key));
            if (\in_array($key, ['CONTENT_TYPE', 'CONTENT_LENGTH', 'CONTENT_MD5'], true)) {
                $_SERVER[$key] = implode(', ', $value);
            } else {
                $_SERVER['HTTP_' . $key] = implode(', ', $value);
            }
        }
        $request = ['g' => $_GET, 'p' => $_POST, 'c' => $_COOKIE];
        $request_order = \ini_get('request_order') ?: \ini_get('variables_order');
        $request_order = preg_replace('#[^cgp]#', '', strtolower($request_order)) ?: 'gp';
        $_REQUEST = [[]];
        foreach (str_split($request_order) as $order) {
            $_REQUEST[] = $request[$order];
        }
        $_REQUEST = array_merge(...$_REQUEST);
    }
    /**
     * Sets a list of trusted proxies.
     *
     * You should only list the reverse proxies that you manage directly.
     *
     * @param array                          $proxies          A list of trusted proxies, the string 'REMOTE_ADDR' will be replaced with $_SERVER['REMOTE_ADDR'] and 'PRIVATE_SUBNETS' by IpUtils::PRIVATE_SUBNETS
     * @param int-mask-of<Request::HEADER_*> $trustedHeaderSet A bit field to set which headers to trust from your proxies
     */
    public static function set_trusted_proxies(array $proxies, int $trusted_header_set): void
    {
        if (false !== $i = array_search('REMOTE_ADDR', $proxies, true)) {
            if (isset($_SERVER['REMOTE_ADDR'])) {
                $proxies[$i] = $_SERVER['REMOTE_ADDR'];
            } else {
                unset($proxies[$i]);
                $proxies = array_values($proxies);
            }
        }
        if (false !== ($i = array_search('PRIVATE_SUBNETS', $proxies, true)) || false !== $i = array_search('private_ranges', $proxies, true)) {
            unset($proxies[$i]);
            $proxies = array_merge($proxies, Ip_Utils::PRIVATE_SUBNETS);
        }
        self::$trusted_proxies = $proxies;
        self::$trusted_header_set = $trusted_header_set;
    }
    /**
     * Gets the list of trusted proxies.
     *
     * @return string[]
     */
    public static function get_trusted_proxies(): array
    {
        return self::$trusted_proxies;
    }
    /**
     * Gets the set of trusted headers from trusted proxies.
     *
     * @return int A bit field of Request::HEADER_* that defines which headers are trusted from your proxies
     */
    public static function get_trusted_header_set(): int
    {
        return self::$trusted_header_set;
    }
    /**
     * Sets a list of trusted host patterns.
     *
     * You should only list the hosts you manage using regexs.
     *
     * @param array $hostPatterns A list of trusted host patterns
     */
    public static function set_trusted_hosts(array $host_patterns): void
    {
        self::$trusted_host_patterns = array_map(static fn(string $host_pattern): string => \sprintf('{%s}i', $host_pattern), $host_patterns);
        // we need to reset trusted hosts on trusted host patterns change
        self::$trusted_hosts = [];
    }
    /**
     * Gets the list of trusted host patterns.
     *
     * @return string[]
     */
    public static function get_trusted_hosts(): array
    {
        return self::$trusted_host_patterns;
    }
    /**
     * Normalizes a query string.
     *
     * It builds a normalized query string, where keys/value pairs are alphabetized,
     * have consistent escaping and unneeded delimiters are removed.
     */
    public static function normalize_query_string(?string $qs): string
    {
        if ('' === ($qs ?? '')) {
            return '';
        }
        $qs = Header_Utils::parse_query($qs);
        ksort($qs);
        return http_build_query($qs, '', '&', \PHP_QUERY_RFC3986);
    }
    /**
     * Enables support for the _method request parameter to determine the intended HTTP method.
     *
     * Be warned that enabling this feature might lead to CSRF issues in your code.
     * Check that you are using CSRF tokens when required.
     * If the HTTP method parameter override is enabled, an html-form with method "POST" can be altered
     * and used to send a "PUT" or "DELETE" request via the _method request parameter.
     * If these methods are not protected against CSRF, this presents a possible vulnerability.
     *
     * The HTTP method can only be overridden when the real HTTP method is POST.
     */
    public static function enable_http_method_parameter_override(): void
    {
        self::$http_method_parameter_override = true;
    }
    /**
     * Checks whether support for the _method request parameter is enabled.
     */
    public static function get_http_method_parameter_override(): bool
    {
        return self::$http_method_parameter_override;
    }
    /**
     * Sets the list of HTTP methods that can be overridden.
     *
     * Set to null to allow all methods to be overridden (default). Set to an
     * empty array to disallow overrides entirely. Otherwise, provide the list
     * of uppercased method names that are allowed.
     *
     * @param uppercase-string[]|null $methods
     */
    public static function set_allowed_http_method_override(?array $methods): void
    {
        if (array_intersect($methods ?? [], ['GET', 'HEAD', 'CONNECT', 'TRACE'])) {
            throw new \InvalidArgumentException('The HTTP methods "GET", "HEAD", "CONNECT", and "TRACE" cannot be overridden.');
        }
        self::$allowed_http_method_override = $methods;
    }
    /**
     * Gets the list of HTTP methods that can be overridden.
     *
     * @return uppercase-string[]|null
     */
    public static function get_allowed_http_method_override(): ?array
    {
        return self::$allowed_http_method_override;
    }
    /**
     * Gets the Session.
     *
     * @throws SessionNotFoundException When session is not set properly
     */
    public function get_session(): Session_Interface
    {
        $session = $this->session;
        if (!$session instanceof Session_Interface && null !== $session) {
            $this->set_session($session = $session());
        }
        if (null === $session) {
            throw new Session_Not_Found_Exception('Session has not been set.');
        }
        return $session;
    }
    /**
     * Whether the request contains a Session which was started in one of the
     * previous requests.
     */
    public function has_previous_session(): bool
    {
        // the check for $this->session avoids malicious users trying to fake a session cookie with proper name
        return $this->has_session() && $this->cookies->has($this->get_session()->get_name());
    }
    /**
     * Whether the request contains a Session object.
     *
     * This method does not give any information about the state of the session object,
     * like whether the session is started or not. It is just a way to check if this Request
     * is associated with a Session instance.
     *
     * @param bool $skipIfUninitialized When true, ignores factories injected by `setSessionFactory`
     */
    public function has_session(bool $skip_if_uninitialized = false): bool
    {
        return null !== $this->session && (!$skip_if_uninitialized || $this->session instanceof Session_Interface);
    }
    public function set_session(Session_Interface $session): void
    {
        $this->session = $session;
    }
    /**
     * @internal
     *
     * @param callable(): SessionInterface $factory
     */
    public function set_session_factory(callable $factory): void
    {
        $this->session = $factory(...);
    }
    /**
     * Returns the client IP addresses.
     *
     * In the returned array the most trusted IP address is first, and the
     * least trusted one last. The "real" client IP address is the last one,
     * but this is also the least trusted one. Trusted proxies are stripped.
     *
     * Use this method carefully; you should use getClientIp() instead.
     *
     * @see getClientIp()
     */
    public function get_client_ips(): array
    {
        $ip = $this->server->get('REMOTE_ADDR');
        if (!$this->is_from_trusted_proxy()) {
            return [$ip];
        }
        return $this->get_trusted_values(self::HEADER_X_FORWARDED_FOR, $ip) ?: [$ip];
    }
    /**
     * Returns the client IP address.
     *
     * This method can read the client IP address from the "X-Forwarded-For" header
     * when trusted proxies were set via "setTrustedProxies()". The "X-Forwarded-For"
     * header value is a comma+space separated list of IP addresses, the left-most
     * being the original client, and each successive proxy that passed the request
     * adding the IP address where it received the request from.
     *
     * If your reverse proxy uses a different header name than "X-Forwarded-For",
     * ("Client-Ip" for instance), configure it via the $trustedHeaderSet
     * argument of the Request::setTrustedProxies() method instead.
     *
     * @see getClientIps()
     * @see https://wikipedia.org/wiki/X-Forwarded-For
     */
    public function get_client_ip(): ?string
    {
        return $this->get_client_ips()[0];
    }
    /**
     * Returns current script name.
     */
    public function get_script_name(): string
    {
        return $this->server->get('SCRIPT_NAME', $this->server->get('ORIG_SCRIPT_NAME', ''));
    }
    /**
     * Returns the path being requested relative to the executed script.
     *
     * The path info always starts with a /.
     *
     * Suppose this request is instantiated from /mysite on localhost:
     *
     *  * http://localhost/mysite              returns '/'
     *  * http://localhost/mysite/about        returns '/about'
     *  * http://localhost/mysite/enco%20ded   returns '/enco%20ded'
     *  * http://localhost/mysite/about?var=1  returns '/about'
     *
     * @return string The raw path (i.e. not urldecoded)
     */
    public function get_path_info(): string
    {
        return $this->path_info ??= $this->prepare_path_info();
    }
    /**
     * Returns the root path from which this request is executed.
     *
     * Suppose that an index.php file instantiates this request object:
     *
     *  * http://localhost/index.php         returns an empty string
     *  * http://localhost/index.php/page    returns an empty string
     *  * http://localhost/web/index.php     returns '/web'
     *  * http://localhost/we%20b/index.php  returns '/we%20b'
     *
     * @return string The raw path (i.e. not urldecoded)
     */
    public function get_base_path(): string
    {
        return $this->base_path ??= $this->prepare_base_path();
    }
    /**
     * Returns the root URL from which this request is executed.
     *
     * The base URL never ends with a /.
     *
     * This is similar to getBasePath(), except that it also includes the
     * script filename (e.g. index.php) if one exists.
     *
     * @return string The raw URL (i.e. not urldecoded)
     */
    public function get_base_url(): string
    {
        $trusted_prefix = '';
        // the proxy prefix must be prepended to any prefix being needed at the webserver level
        if ($this->is_from_trusted_proxy() && $trusted_prefix_values = $this->get_trusted_values(self::HEADER_X_FORWARDED_PREFIX)) {
            $trusted_prefix = rtrim((string) $trusted_prefix_values[0], '/');
        }
        return $trusted_prefix . $this->get_base_url_real();
    }
    /**
     * Returns the real base URL received by the webserver from which this request is executed.
     * The URL does not include trusted reverse proxy prefix.
     *
     * @return string The raw URL (i.e. not urldecoded)
     */
    private function get_base_url_real(): string
    {
        return $this->base_url ??= $this->prepare_base_url();
    }
    /**
     * Gets the request's scheme.
     */
    public function get_scheme(): string
    {
        return $this->is_secure() ? 'https' : 'http';
    }
    /**
     * Returns the port on which the request is made.
     *
     * This method can read the client port from the "X-Forwarded-Port" header
     * when trusted proxies were set via "setTrustedProxies()".
     *
     * The "X-Forwarded-Port" header must contain the client port.
     *
     * @return int|string|null Can be a string if fetched from the server bag
     */
    public function get_port(): int|string|null
    {
        if ($this->is_from_trusted_proxy() && $host = $this->get_trusted_values(self::HEADER_X_FORWARDED_PORT)) {
            $host = $host[0];
        } elseif ($this->is_from_trusted_proxy() && $host = $this->get_trusted_values(self::HEADER_X_FORWARDED_HOST)) {
            $host = $host[0];
        } elseif (!$host = $this->headers->get('HOST')) {
            return $this->server->get('SERVER_PORT');
        }
        if ('[' === $host[0]) {
            $pos = strpos((string) $host, ':', strrpos((string) $host, ']'));
        } else {
            $pos = strrpos((string) $host, ':');
        }
        if (false !== $pos && $port = substr((string) $host, $pos + 1)) {
            return (int) $port;
        }
        return 'https' === $this->get_scheme() ? 443 : 80;
    }
    /**
     * Returns the user.
     */
    public function get_user(): ?string
    {
        return $this->headers->get('PHP_AUTH_USER');
    }
    /**
     * Returns the password.
     */
    public function get_password(): ?string
    {
        return $this->headers->get('PHP_AUTH_PW');
    }
    /**
     * Gets the user info.
     *
     * @return string|null A user name if any and, optionally, scheme-specific information about how to gain authorization to access the server
     */
    public function get_user_info(): ?string
    {
        $userinfo = $this->get_user();
        $pass = $this->get_password();
        if ('' != $pass) {
            $userinfo .= ":{$pass}";
        }
        return $userinfo;
    }
    /**
     * Returns the HTTP host being requested.
     *
     * The port name will be appended to the host if it's non-standard.
     */
    public function get_http_host(): string
    {
        $scheme = $this->get_scheme();
        $port = $this->get_port();
        if ('http' === $scheme && 80 == $port || 'https' === $scheme && 443 == $port) {
            return $this->get_host();
        }
        return $this->get_host() . ':' . $port;
    }
    /**
     * Returns the requested URI (path and query string).
     *
     * @return string The raw URI (i.e. not URI decoded)
     */
    public function get_request_uri(): string
    {
        return $this->request_uri ??= $this->prepare_request_uri();
    }
    /**
     * Gets the scheme and HTTP host.
     *
     * If the URL was called with basic authentication, the user
     * and the password are not added to the generated string.
     */
    public function get_scheme_and_http_host(): string
    {
        return $this->get_scheme() . '://' . $this->get_http_host();
    }
    /**
     * Generates a normalized URI (URL) for the Request.
     *
     * @see getQueryString()
     */
    public function get_uri(): string
    {
        if (null !== $qs = $this->get_query_string()) {
            $qs = '?' . $qs;
        }
        return $this->get_scheme_and_http_host() . $this->get_base_url() . $this->get_path_info() . $qs;
    }
    /**
     * Generates a normalized URI for the given path.
     *
     * @param string $path A path to use instead of the current one
     */
    public function get_uri_for_path(string $path): string
    {
        return $this->get_scheme_and_http_host() . $this->get_base_url() . $path;
    }
    /**
     * Returns the path as relative reference from the current Request path.
     *
     * Only the URIs path component (no schema, host etc.) is relevant and must be given.
     * Both paths must be absolute and not contain relative parts.
     * Relative URLs from one resource to another are useful when generating self-contained downloadable document archives.
     * Furthermore, they can be used to reduce the link size in documents.
     *
     * Example target paths, given a base path of "/a/b/c/d":
     * - "/a/b/c/d"     -> ""
     * - "/a/b/c/"      -> "./"
     * - "/a/b/"        -> "../"
     * - "/a/b/c/other" -> "other"
     * - "/a/x/y"       -> "../../x/y"
     */
    public function get_relative_uri_for_path(string $path): string
    {
        // be sure that we are dealing with an absolute path
        if (!isset($path[0]) || '/' !== $path[0]) {
            return $path;
        }
        if ($path === $base_path = $this->get_path_info()) {
            return '';
        }
        $source_dirs = explode('/', isset($base_path[0]) && '/' === $base_path[0] ? substr($base_path, 1) : $base_path);
        $target_dirs = explode('/', substr($path, 1));
        array_pop($source_dirs);
        $target_file = array_pop($target_dirs);
        foreach ($source_dirs as $i => $dir) {
            if (isset($target_dirs[$i]) && $dir === $target_dirs[$i]) {
                unset($source_dirs[$i], $target_dirs[$i]);
            } else {
                break;
            }
        }
        $target_dirs[] = $target_file;
        $path = str_repeat('../', \count($source_dirs)) . implode('/', $target_dirs);
        // A reference to the same base directory or an empty subdirectory must be prefixed with "./".
        // This also applies to a segment with a colon character (e.g., "file:colon") that cannot be used
        // as the first segment of a relative-path reference, as it would be mistaken for a scheme name
        // (see https://tools.ietf.org/html/rfc3986#section-4.2).
        return !isset($path[0]) || '/' === $path[0] || false !== ($colon_pos = strpos($path, ':')) && ($colon_pos < ($slash_pos = strpos($path, '/')) || false === $slash_pos) ? "./{$path}" : $path;
    }
    /**
     * Generates the normalized query string for the Request.
     *
     * It builds a normalized query string, where keys/value pairs are alphabetized
     * and have consistent escaping.
     */
    public function get_query_string(): ?string
    {
        $qs = static::normalize_query_string($this->server->get('QUERY_STRING'));
        return '' === $qs ? null : $qs;
    }
    /**
     * Checks whether the request is secure or not.
     *
     * This method can read the client protocol from the "X-Forwarded-Proto" header
     * when trusted proxies were set via "setTrustedProxies()".
     *
     * The "X-Forwarded-Proto" header must contain the protocol: "https" or "http".
     */
    public function is_secure(): bool
    {
        if ($this->is_from_trusted_proxy() && $proto = $this->get_trusted_values(self::HEADER_X_FORWARDED_PROTO)) {
            return \in_array(strtolower((string) $proto[0]), ['https', 'on', 'ssl', '1'], true);
        }
        $https = $this->server->get('HTTPS');
        return $https && (!\is_string($https) || 'off' !== strtolower($https));
    }
    /**
     * Returns the host name.
     *
     * This method can read the client host name from the "X-Forwarded-Host" header
     * when trusted proxies were set via "setTrustedProxies()".
     *
     * The "X-Forwarded-Host" header must contain the client host name.
     *
     * @throws SuspiciousOperationException when the host name is invalid or not trusted
     */
    public function get_host(): string
    {
        if ($this->is_from_trusted_proxy() && $host = $this->get_trusted_values(self::HEADER_X_FORWARDED_HOST)) {
            $host = $host[0];
        } else {
            $host = ($this->headers->get('HOST') ?: $this->server->get('SERVER_NAME')) ?: $this->server->get('SERVER_ADDR', '');
        }
        // trim and remove port number from host
        // host is lowercase as per RFC 952/2181
        $host = strtolower((string) preg_replace('/:\d+$/', '', trim((string) $host)));
        // the host can come from the user (HTTP_HOST and depending on the configuration, SERVER_NAME too can come from the user)
        if ($host && !self::is_host_valid($host)) {
            if (!$this->is_host_valid) {
                return '';
            }
            $this->is_host_valid = false;
            throw new Suspicious_Operation_Exception(\sprintf('Invalid Host "%s".', $host));
        }
        if (\count(self::$trusted_host_patterns) > 0) {
            // to avoid host header injection attacks, you should provide a list of trusted host patterns
            if (\in_array($host, self::$trusted_hosts, true)) {
                return $host;
            }
            foreach (self::$trusted_host_patterns as $pattern) {
                if (preg_match($pattern, $host)) {
                    self::$trusted_hosts[] = $host;
                    return $host;
                }
            }
            if (!$this->is_host_valid) {
                return '';
            }
            $this->is_host_valid = false;
            throw new Suspicious_Operation_Exception(\sprintf('Untrusted Host "%s".', $host));
        }
        return $host;
    }
    /**
     * Sets the request method.
     */
    public function set_method(string $method): void
    {
        $this->method = null;
        $this->server->set('REQUEST_METHOD', $method);
    }
    /**
     * Gets the request "intended" method.
     *
     * If the X-HTTP-Method-Override header is set, and if the method is a POST,
     * then it is used to determine the "real" intended HTTP method.
     *
     * The _method request parameter can also be used to determine the HTTP method,
     * but only if enableHttpMethodParameterOverride() has been called.
     *
     * The method is always an uppercased string.
     *
     * @see getRealMethod()
     */
    public function get_method(): string
    {
        if (null !== $this->method) {
            return $this->method;
        }
        $this->method = strtoupper((string) $this->server->get('REQUEST_METHOD', 'GET'));
        if ('POST' !== $this->method || !(self::$allowed_http_method_override ?? true)) {
            return $this->method;
        }
        $method = $this->headers->get('X-HTTP-METHOD-OVERRIDE');
        if (!$method && self::$http_method_parameter_override) {
            $method = $this->request->get('_method', $this->query->get('_method', 'POST'));
        }
        if (!\is_string($method)) {
            return $this->method;
        }
        $method = strtoupper($method);
        if (\in_array($method, ['GET', 'HEAD', 'CONNECT', 'TRACE'], true)) {
            return $this->method;
        }
        if (self::$allowed_http_method_override && !\in_array($method, self::$allowed_http_method_override, true)) {
            return $this->method;
        }
        if (\strlen($method) !== strspn($method, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ')) {
            throw new Suspicious_Operation_Exception('Invalid HTTP method override.');
        }
        return $this->method = $method;
    }
    /**
     * Gets the "real" request method.
     *
     * @see getMethod()
     */
    public function get_real_method(): string
    {
        return strtoupper((string) $this->server->get('REQUEST_METHOD', 'GET'));
    }
    /**
     * Gets the mime type associated with the format.
     */
    public function get_mime_type(string $format): ?string
    {
        if (null === static::$formats) {
            static::initialize_formats();
        }
        return isset(static::$formats[$format]) ? static::$formats[$format][0] : null;
    }
    /**
     * Gets the mime types associated with the format.
     *
     * @return string[]
     */
    public static function get_mime_types(string $format): array
    {
        if (null === static::$formats) {
            static::initialize_formats();
        }
        return static::$formats[$format] ?? [];
    }
    /**
     * Gets the format associated with the mime type.
     *
     *  Resolution order:
     *   1) Exact match on the full MIME type (e.g. "application/json").
     *   2) Match on the canonical MIME type (i.e. before the first ";" parameter).
     *   3) If the type is "application/*+suffix", use the structured syntax suffix
     *      mapping (e.g. "application/foo+json" → "json"), when available.
     *   4) If $subtypeFallback is true and no match was found:
     *      - return the MIME subtype (without "x-" prefix), provided it does not
     *        contain a "+" (e.g. "application/x-yaml" → "yaml", "text/csv" → "csv").
     *
     * @param string|null $mimeType        The mime type to check
     * @param bool        $subtypeFallback Whether to fall back to the subtype if no exact match is found
     */
    public function get_format(?string $mime_type, bool $subtype_fallback = false): ?string
    {
        $canonical_mime_type = null;
        if ($mime_type && false !== $pos = strpos($mime_type, ';')) {
            $canonical_mime_type = trim(substr($mime_type, 0, $pos));
        }
        if (null === static::$formats) {
            static::initialize_formats();
        }
        $exact_format = null;
        $canonical_format = null;
        foreach (static::$formats as $format => $mime_types) {
            if (\in_array($mime_type, $mime_types, true)) {
                $exact_format = $format;
            }
            if (null !== $canonical_mime_type && \in_array($canonical_mime_type, $mime_types, true)) {
                $canonical_format = $format;
            }
        }
        if ($format = $exact_format ?? $canonical_format) {
            return $format;
        }
        if (!$canonical_mime_type ??= $mime_type) {
            return null;
        }
        if (str_starts_with($canonical_mime_type, 'application/') && str_contains($canonical_mime_type, '+')) {
            $suffix = substr(strrchr($canonical_mime_type, '+'), 1);
            if (isset(self::STRUCTURED_SUFFIX_FORMATS[$suffix])) {
                return self::STRUCTURED_SUFFIX_FORMATS[$suffix];
            }
        }
        if ($subtype_fallback && str_contains($canonical_mime_type, '/')) {
            [, $subtype] = explode('/', $canonical_mime_type, 2);
            if (str_starts_with($subtype, 'x-')) {
                $subtype = substr($subtype, 2);
            }
            if (!str_contains($subtype, '+')) {
                return $subtype;
            }
        }
        return null;
    }
    /**
     * Associates a format with mime types.
     *
     * @param string|string[] $mimeTypes The associated mime types (the preferred one must be the first as it will be used as the content type)
     */
    public function set_format(string $format, string|array $mime_types): void
    {
        if (null === static::$formats) {
            static::initialize_formats();
        }
        static::$formats[$format] = (array) $mime_types;
    }
    /**
     * Gets the request format.
     *
     * Here is the process to determine the format:
     *
     *  * format defined by the user (with setRequestFormat())
     *  * _format request attribute
     *  * $default
     *
     * @see getPreferredFormat
     */
    public function get_request_format(?string $default = 'html'): ?string
    {
        $this->format ??= $this->attributes->get('_format');
        return $this->format ?? $default;
    }
    /**
     * Sets the request format.
     */
    public function set_request_format(?string $format): void
    {
        $this->format = $format;
    }
    /**
     * Gets the usual name of the format associated with the request's media type (provided in the Content-Type header).
     *
     * @see Request::$formats
     */
    public function get_content_type_format(): ?string
    {
        return $this->get_format($this->headers->get('CONTENT_TYPE', ''));
    }
    /**
     * Sets the default locale.
     */
    public function set_default_locale(string $locale): void
    {
        $this->default_locale = $locale;
        if (null === $this->locale) {
            $this->set_php_default_locale($locale);
        }
    }
    /**
     * Get the default locale.
     */
    public function get_default_locale(): string
    {
        return $this->default_locale;
    }
    /**
     * Sets the locale.
     */
    public function set_locale(string $locale): void
    {
        $this->set_php_default_locale($this->locale = $locale);
    }
    /**
     * Get the locale.
     */
    public function get_locale(): string
    {
        return $this->locale ?? $this->default_locale;
    }
    /**
     * Checks if the request method is of specified type.
     *
     * @param string $method Uppercase request method (GET, POST etc)
     */
    public function is_method(string $method): bool
    {
        return $this->get_method() === strtoupper($method);
    }
    /**
     * Checks whether or not the method is safe.
     *
     * @see https://tools.ietf.org/html/rfc7231#section-4.2.1
     */
    public function is_method_safe(): bool
    {
        return \in_array($this->get_method(), ['GET', 'HEAD', 'OPTIONS', 'TRACE', 'QUERY'], true);
    }
    /**
     * Checks whether or not the method is idempotent.
     */
    public function is_method_idempotent(): bool
    {
        return \in_array($this->get_method(), ['HEAD', 'GET', 'PUT', 'DELETE', 'TRACE', 'OPTIONS', 'PURGE', 'QUERY'], true);
    }
    /**
     * Checks whether the method is cacheable or not.
     *
     * @see https://tools.ietf.org/html/rfc7231#section-4.2.3
     */
    public function is_method_cacheable(): bool
    {
        return \in_array($this->get_method(), ['GET', 'HEAD', 'QUERY'], true);
    }
    /**
     * Returns the protocol version.
     *
     * If the application is behind a proxy, the protocol version used in the
     * requests between the client and the proxy and between the proxy and the
     * server might be different. This returns the former (from the "Via" header)
     * if the proxy is trusted (see "setTrustedProxies()"), otherwise it returns
     * the latter (from the "SERVER_PROTOCOL" server parameter).
     */
    public function get_protocol_version(): ?string
    {
        if ($this->is_from_trusted_proxy()) {
            preg_match('~^(HTTP/)?([1-9]\.[0-9])\b~', $this->headers->get('Via') ?? '', $matches);
            if ($matches) {
                return 'HTTP/' . $matches[2];
            }
        }
        return $this->server->get('SERVER_PROTOCOL');
    }
    /**
     * Returns the request body content.
     *
     * @param bool $asResource If true, a resource will be returned
     *
     * @return string|resource
     *
     * @psalm-return ($asResource is true ? resource : string)
     */
    public function get_content(bool $as_resource = false)
    {
        if ($as_resource) {
            if (\is_resource($this->content)) {
                rewind($this->content);
                return $this->content;
            }
            // Content passed in parameter (test)
            if (\is_string($this->content)) {
                $resource = fopen('php://temp', 'r+');
                fwrite($resource, $this->content);
                rewind($resource);
                return $resource;
            }
            $this->content = false;
            return fopen('php://input', 'r');
        }
        if (\is_resource($this->content)) {
            rewind($this->content);
            return stream_get_contents($this->content);
        }
        if (null === $this->content || false === $this->content) {
            $this->content = file_get_contents('php://input');
        }
        return $this->content;
    }
    /**
     * Gets the decoded form or json request body.
     *
     * @throws JsonException When the body cannot be decoded to an array
     */
    public function get_payload(): Input_Bag
    {
        if ($this->request->count()) {
            return clone $this->request;
        }
        if ('' === $content = $this->get_content()) {
            return new Input_Bag([]);
        }
        try {
            $content = json_decode($content, true, 512, \JSON_BIGINT_AS_STRING | \JSON_THROW_ON_ERROR);
        } catch (\Json_Exception $e) {
            throw new Json_Exception('Could not decode request body.', $e->get_code(), $e);
        }
        if (!\is_array($content)) {
            throw new Json_Exception(\sprintf('JSON content was expected to decode to an array, "%s" returned.', get_debug_type($content)));
        }
        return new Input_Bag($content);
    }
    /**
     * Gets the request body decoded as array, typically from a JSON payload.
     *
     * @see getPayload() for portability between content types
     *
     * @throws JsonException When the body cannot be decoded to an array
     */
    public function to_array(): array
    {
        if ('' === $content = $this->get_content()) {
            throw new Json_Exception('Request body is empty.');
        }
        try {
            $content = json_decode($content, true, 512, \JSON_BIGINT_AS_STRING | \JSON_THROW_ON_ERROR);
        } catch (\Json_Exception $e) {
            throw new Json_Exception('Could not decode request body.', $e->get_code(), $e);
        }
        if (!\is_array($content)) {
            throw new Json_Exception(\sprintf('JSON content was expected to decode to an array, "%s" returned.', get_debug_type($content)));
        }
        return $content;
    }
    /**
     * Gets the Etags.
     */
    public function get_e_tags(): array
    {
        return preg_split('/\s*,\s*/', (string) $this->headers->get('If-None-Match', ''), -1, \PREG_SPLIT_NO_EMPTY);
    }
    public function is_no_cache(): bool
    {
        if ($this->headers->has_cache_control_directive('no-cache')) {
            return true;
        }
        return 'no-cache' == $this->headers->get('Pragma');
    }
    /**
     * Gets the preferred format for the response by inspecting, in the following order:
     *   * the request format set using setRequestFormat;
     *   * the values of the Accept HTTP header.
     *
     * Note that if you use this method, you should send the "Vary: Accept" header
     * in the response to prevent any issues with intermediary HTTP caches.
     */
    public function get_preferred_format(?string $default = 'html'): ?string
    {
        if (!isset($this->preferred_format) && null !== $preferred_format = $this->get_request_format(null)) {
            $this->preferred_format = $preferred_format;
        }
        if ($this->preferred_format ?? null) {
            return $this->preferred_format;
        }
        foreach ($this->get_acceptable_content_types() as $mime_type) {
            if ($this->preferred_format = $this->get_format($mime_type)) {
                return $this->preferred_format;
            }
        }
        return $default;
    }
    /**
     * Returns the preferred language.
     *
     * @param string[] $locales An array of ordered available locales
     */
    public function get_preferred_language(?array $locales = null): ?string
    {
        $preferred_languages = $this->get_languages();
        if (!$locales) {
            return $preferred_languages[0] ?? null;
        }
        $locales = array_map($this->format_locale(...), $locales);
        if (!$preferred_languages) {
            return $locales[0];
        }
        $combinations = array_merge(...array_map($this->get_language_combinations(...), $preferred_languages));
        foreach ($combinations as $combination) {
            foreach ($locales as $locale) {
                if (str_starts_with($locale, $combination)) {
                    return $locale;
                }
            }
        }
        return $locales[0];
    }
    /**
     * Gets a list of languages acceptable by the client browser ordered in the user browser preferences.
     *
     * @return string[]
     */
    public function get_languages(): array
    {
        if (null !== $this->languages) {
            return $this->languages;
        }
        $languages = Accept_Header::from_string($this->headers->get('Accept-Language'))->all();
        $this->languages = [];
        foreach ($languages as $accept_header_item) {
            $lang = $accept_header_item->get_value();
            $this->languages[] = self::format_locale($lang);
        }
        $this->languages = array_unique($this->languages);
        return $this->languages;
    }
    /**
     * Strips the locale to only keep the canonicalized language value.
     *
     * Depending on the $locale value, this method can return values like :
     * - language_Script_REGION: "fr_Latn_FR", "zh_Hans_TW"
     * - language_Script: "fr_Latn", "zh_Hans"
     * - language_REGION: "fr_FR", "zh_TW"
     * - language: "fr", "zh"
     *
     * Invalid locale values are returned as is.
     *
     * @see https://wikipedia.org/wiki/IETF_language_tag
     * @see https://datatracker.ietf.org/doc/html/rfc5646
     */
    private static function format_locale(string $locale): string
    {
        [$language, $script, $region] = self::get_language_components($locale);
        return implode('_', array_filter([$language, $script, $region]));
    }
    /**
     * Returns an array of all possible combinations of the language components.
     *
     * For instance, if the locale is "fr_Latn_FR", this method will return:
     * - "fr_Latn_FR"
     * - "fr_Latn"
     * - "fr_FR"
     * - "fr"
     *
     * @return string[]
     */
    private static function get_language_combinations(string $locale): array
    {
        [$language, $script, $region] = self::get_language_components($locale);
        return array_unique([implode('_', array_filter([$language, $script, $region])), implode('_', array_filter([$language, $script])), implode('_', array_filter([$language, $region])), $language]);
    }
    /**
     * Returns an array with the language components of the locale.
     *
     * For example:
     * - If the locale is "fr_Latn_FR", this method will return "fr", "Latn", "FR"
     * - If the locale is "fr_FR", this method will return "fr", null, "FR"
     * - If the locale is "zh_Hans", this method will return "zh", "Hans", null
     *
     * @see https://wikipedia.org/wiki/IETF_language_tag
     * @see https://datatracker.ietf.org/doc/html/rfc5646
     *
     * @return array{string, string|null, string|null}
     */
    private static function get_language_components(string $locale): array
    {
        $locale = str_replace('_', '-', strtolower($locale));
        $pattern = '/^([a-zA-Z]{2,3}|i-[a-zA-Z]{5,})(?:-([a-zA-Z]{4}))?(?:-([a-zA-Z]{2}))?(?:-(.+))?$/';
        if (!preg_match($pattern, $locale, $matches)) {
            return [$locale, null, null];
        }
        if (str_starts_with($matches[1], 'i-')) {
            // Language not listed in ISO 639 that are not variants
            // of any listed language, which can be registered with the
            // i-prefix, such as i-cherokee
            $matches[1] = substr($matches[1], 2);
        }
        return [$matches[1], isset($matches[2]) ? ucfirst(strtolower($matches[2])) : null, isset($matches[3]) ? strtoupper($matches[3]) : null];
    }
    /**
     * Gets a list of charsets acceptable by the client browser in preferable order.
     *
     * @return string[]
     */
    public function get_charsets(): array
    {
        return $this->charsets ??= array_map(strval(...), array_keys(Accept_Header::from_string($this->headers->get('Accept-Charset'))->all()));
    }
    /**
     * Gets a list of encodings acceptable by the client browser in preferable order.
     *
     * @return string[]
     */
    public function get_encodings(): array
    {
        return $this->encodings ??= array_map(strval(...), array_keys(Accept_Header::from_string($this->headers->get('Accept-Encoding'))->all()));
    }
    /**
     * Gets a list of content types acceptable by the client browser in preferable order.
     *
     * @return string[]
     */
    public function get_acceptable_content_types(): array
    {
        return $this->acceptable_content_types ??= array_map(strval(...), array_keys(Accept_Header::from_string($this->headers->get('Accept'))->all()));
    }
    /**
     * Returns true if the request is an XMLHttpRequest.
     *
     * It works if your JavaScript library sets an X-Requested-With HTTP header.
     * It is known to work with common JavaScript frameworks:
     *
     * @see https://wikipedia.org/wiki/List_of_Ajax_frameworks#JavaScript
     */
    public function is_xml_http_request(): bool
    {
        return 'XMLHttpRequest' == $this->headers->get('X-Requested-With');
    }
    /**
     * Checks whether the client browser prefers safe content or not according to RFC8674.
     *
     * @see https://tools.ietf.org/html/rfc8674
     */
    public function prefer_safe_content(): bool
    {
        if (isset($this->is_safe_content_preferred)) {
            return $this->is_safe_content_preferred;
        }
        if (!$this->is_secure()) {
            // see https://tools.ietf.org/html/rfc8674#section-3
            return $this->is_safe_content_preferred = false;
        }
        return $this->is_safe_content_preferred = Accept_Header::from_string($this->headers->get('Prefer'))->has('safe');
    }
    /*
     * The following methods are derived from code of the Zend Framework (1.10dev - 2010-01-24)
     *
     * Code subject to the new BSD license (https://framework.zend.com/license).
     *
     * Copyright (c) 2005-2010 Zend Technologies USA Inc. (https://www.zend.com/)
     */
    protected function prepare_request_uri(): string
    {
        $request_uri = '';
        if ($this->is_iis_rewrite() && '' != $this->server->get('UNENCODED_URL')) {
            // IIS7 with URL Rewrite: make sure we get the unencoded URL (double slash problem)
            $request_uri = $this->server->get('UNENCODED_URL');
            $this->server->remove('UNENCODED_URL');
        } elseif ($this->server->has('REQUEST_URI')) {
            $request_uri = $this->server->get('REQUEST_URI');
            if ('' !== $request_uri && '/' === $request_uri[0]) {
                // To only use path and query remove the fragment.
                if (false !== $pos = strpos((string) $request_uri, '#')) {
                    $request_uri = substr((string) $request_uri, 0, $pos);
                }
            } else {
                // HTTP proxy reqs setup request URI with scheme and host [and port] + the URL path,
                // only use URL path.
                $uri_components = parse_url((string) $request_uri);
                if (isset($uri_components['path'])) {
                    $request_uri = $uri_components['path'];
                }
                if (isset($uri_components['query'])) {
                    $request_uri .= '?' . $uri_components['query'];
                }
            }
        } elseif ($this->server->has('ORIG_PATH_INFO')) {
            // IIS 5.0, PHP as CGI
            $request_uri = $this->server->get('ORIG_PATH_INFO');
            if ('' != $this->server->get('QUERY_STRING')) {
                $request_uri .= '?' . $this->server->get('QUERY_STRING');
            }
            $this->server->remove('ORIG_PATH_INFO');
        }
        // normalize the request URI to ease creating sub-requests from this request
        $this->server->set('REQUEST_URI', $request_uri);
        return $request_uri;
    }
    /**
     * Prepares the base URL.
     */
    protected function prepare_base_url(): string
    {
        $filename = basename((string) $this->server->get('SCRIPT_FILENAME', ''));
        if (basename((string) $this->server->get('SCRIPT_NAME', '')) === $filename) {
            $base_url = $this->server->get('SCRIPT_NAME');
        } elseif (basename((string) $this->server->get('PHP_SELF', '')) === $filename) {
            $base_url = $this->server->get('PHP_SELF');
        } elseif (basename((string) $this->server->get('ORIG_SCRIPT_NAME', '')) === $filename) {
            $base_url = $this->server->get('ORIG_SCRIPT_NAME');
            // 1and1 shared hosting compatibility
        } else {
            // Backtrack up the script_filename to find the portion matching
            // php_self
            $path = $this->server->get('PHP_SELF', '');
            $file = $this->server->get('SCRIPT_FILENAME', '');
            $segs = explode('/', trim((string) $file, '/'));
            $segs = array_reverse($segs);
            $index = 0;
            $last = \count($segs);
            $base_url = '';
            do {
                $seg = $segs[$index];
                $base_url = '/' . $seg . $base_url;
                ++$index;
            } while ($last > $index && false !== ($pos = strpos((string) $path, $base_url)) && 0 != $pos);
        }
        // Does the baseUrl have anything in common with the request_uri?
        $request_uri = $this->get_request_uri();
        if ('' !== $request_uri && '/' !== $request_uri[0]) {
            $request_uri = '/' . $request_uri;
        }
        if ($base_url && null !== $prefix = $this->get_urlencoded_prefix($request_uri, $base_url)) {
            // full $baseUrl matches
            return $prefix;
        }
        if ($base_url && null !== $prefix = $this->get_urlencoded_prefix($request_uri, rtrim(\dirname((string) $base_url), '/' . \DIRECTORY_SEPARATOR) . '/')) {
            // directory portion of $baseUrl matches
            return rtrim($prefix, '/' . \DIRECTORY_SEPARATOR);
        }
        $truncated_request_uri = $request_uri;
        if (false !== $pos = strpos($request_uri, '?')) {
            $truncated_request_uri = substr($request_uri, 0, $pos);
        }
        $basename = basename($base_url ?? '');
        if (!$basename || !strpos(rawurldecode($truncated_request_uri), $basename)) {
            // no match whatsoever; set it blank
            return '';
        }
        // If using mod_rewrite or ISAPI_Rewrite strip the script filename
        // out of baseUrl. $pos !== 0 makes sure it is not matching a value
        // from PATH_INFO or QUERY_STRING
        if (\strlen($request_uri) >= \strlen((string) $base_url) && false !== ($pos = strpos($request_uri, (string) $base_url)) && 0 !== $pos) {
            $base_url = substr($request_uri, 0, $pos + \strlen((string) $base_url));
        }
        return rtrim((string) $base_url, '/' . \DIRECTORY_SEPARATOR);
    }
    /**
     * Prepares the base path.
     */
    protected function prepare_base_path(): string
    {
        $base_url = $this->get_base_url();
        if (!$base_url) {
            return '';
        }
        $filename = basename((string) $this->server->get('SCRIPT_FILENAME'));
        if (basename($base_url) === $filename) {
            $base_path = \dirname($base_url);
        } else {
            $base_path = $base_url;
        }
        if ('\\' === \DIRECTORY_SEPARATOR) {
            $base_path = str_replace('\\', '/', $base_path);
        }
        return rtrim($base_path, '/');
    }
    /**
     * Prepares the path info.
     */
    protected function prepare_path_info(): string
    {
        if (null === $request_uri = $this->get_request_uri()) {
            return '/';
        }
        // Remove the query string from REQUEST_URI
        if (false !== $pos = strpos($request_uri, '?')) {
            $request_uri = substr($request_uri, 0, $pos);
        }
        if ('' !== $request_uri && '/' !== $request_uri[0]) {
            $request_uri = '/' . $request_uri;
        }
        if (null === $base_url = $this->get_base_url_real()) {
            return $request_uri;
        }
        $path_info = substr($request_uri, \strlen($base_url));
        if ('' === $path_info || '/' !== $path_info[0]) {
            return '/' . $path_info;
        }
        return $path_info;
    }
    /**
     * Initializes HTTP request formats.
     */
    protected static function initialize_formats(): void
    {
        static::$formats = ['html' => ['text/html', 'application/xhtml+xml'], 'txt' => ['text/plain'], 'js' => ['application/javascript', 'application/x-javascript', 'text/javascript'], 'css' => ['text/css'], 'json' => ['application/json', 'application/x-json'], 'jsonld' => ['application/ld+json'], 'xml' => ['text/xml', 'application/xml', 'application/x-xml'], 'rdf' => ['application/rdf+xml'], 'atom' => ['application/atom+xml'], 'rss' => ['application/rss+xml'], 'form' => ['application/x-www-form-urlencoded', 'multipart/form-data'], 'soap' => ['application/soap+xml'], 'problem' => ['application/problem+json'], 'hal' => ['application/hal+json', 'application/hal+xml'], 'jsonapi' => ['application/vnd.api+json'], 'yaml' => ['text/yaml', 'application/x-yaml'], 'wbxml' => ['application/vnd.wap.wbxml'], 'pdf' => ['application/pdf'], 'csv' => ['text/csv']];
    }
    private function set_php_default_locale(string $locale): void
    {
        // if either the class Locale doesn't exist, or an exception is thrown when
        // setting the default locale, the intl module is not installed, and
        // the call can be ignored:
        try {
            if (class_exists(\Locale::class, false)) {
                \Locale::set_default($locale);
            }
        } catch (\Exception) {
        }
    }
    /**
     * Returns the prefix as encoded in the string when the string starts with
     * the given prefix, null otherwise.
     */
    private function get_urlencoded_prefix(string $string, string $prefix): ?string
    {
        if ($this->is_iis_rewrite()) {
            // ISS with UrlRewriteModule might report SCRIPT_NAME/PHP_SELF with wrong case
            // see https://github.com/php/php-src/issues/11981
            if (0 !== stripos(rawurldecode($string), $prefix)) {
                return null;
            }
        } elseif (!str_starts_with(rawurldecode($string), $prefix)) {
            return null;
        }
        $len = \strlen($prefix);
        if (preg_match(\sprintf('#^(%%[[:xdigit:]]{2}|.){%d}#', $len), $string, $match)) {
            return $match[0];
        }
        return null;
    }
    private static function create_request_from_factory(array $query = [], array $request = [], array $attributes = [], array $cookies = [], array $files = [], array $server = [], $content = null): static
    {
        if (self::$request_factory) {
            $request = (self::$request_factory)($query, $request, $attributes, $cookies, $files, $server, $content);
            if (!$request instanceof self) {
                throw new \LogicException('The Request factory must return an instance of Symfony\Component\HttpFoundation\Request.');
            }
            return $request;
        }
        return new static($query, $request, $attributes, $cookies, $files, $server, $content);
    }
    /**
     * Indicates whether this request originated from a trusted proxy.
     *
     * This can be useful to determine whether or not to trust the
     * contents of a proxy-specific header.
     */
    public function is_from_trusted_proxy(): bool
    {
        return self::$trusted_proxies && Ip_Utils::check_ip($this->server->get('REMOTE_ADDR', ''), self::$trusted_proxies);
    }
    /**
     * This method is rather heavy because it splits and merges headers, and it's called by many other methods such as
     * getPort(), isSecure(), getHost(), getClientIps(), getBaseUrl() etc. Thus, we try to cache the results for
     * best performance.
     */
    private function get_trusted_values(int $type, ?string $ip = null): array
    {
        $cache_key = $type . "\x00" . (self::$trusted_header_set & $type ? $this->headers->get(self::TRUSTED_HEADERS[$type]) : '');
        $cache_key .= "\x00" . $ip . "\x00" . $this->headers->get(self::TRUSTED_HEADERS[self::HEADER_FORWARDED]);
        if (isset($this->trusted_values_cache[$cache_key])) {
            return $this->trusted_values_cache[$cache_key];
        }
        $client_values = [];
        $forwarded_values = [];
        if (self::$trusted_header_set & $type && $this->headers->has(self::TRUSTED_HEADERS[$type])) {
            foreach (explode(',', (string) $this->headers->get(self::TRUSTED_HEADERS[$type])) as $v) {
                $client_values[] = (self::HEADER_X_FORWARDED_PORT === $type ? '0.0.0.0:' : '') . trim($v);
            }
        }
        if (self::$trusted_header_set & self::HEADER_FORWARDED && isset(self::FORWARDED_PARAMS[$type]) && $this->headers->has(self::TRUSTED_HEADERS[self::HEADER_FORWARDED])) {
            $forwarded = $this->headers->get(self::TRUSTED_HEADERS[self::HEADER_FORWARDED]);
            $parts = Header_Utils::split($forwarded, ',;=');
            $param = self::FORWARDED_PARAMS[$type];
            foreach ($parts as $sub_parts) {
                if (null === $v = Header_Utils::combine($sub_parts)[$param] ?? null) {
                    continue;
                }
                if (self::HEADER_X_FORWARDED_PORT === $type) {
                    if (str_ends_with($v, ']') || false === $v = strrchr($v, ':')) {
                        $v = $this->is_secure() ? ':443' : ':80';
                    }
                    $v = '0.0.0.0' . $v;
                }
                $forwarded_values[] = $v;
            }
        }
        if (null !== $ip) {
            $client_values = $this->normalize_and_filter_client_ips($client_values, $ip);
            $forwarded_values = $this->normalize_and_filter_client_ips($forwarded_values, $ip);
        }
        if ($forwarded_values === $client_values || !$client_values) {
            return $this->trusted_values_cache[$cache_key] = $forwarded_values;
        }
        if (!$forwarded_values) {
            return $this->trusted_values_cache[$cache_key] = $client_values;
        }
        if (!$this->is_forwarded_valid) {
            return $this->trusted_values_cache[$cache_key] = null !== $ip ? ['0.0.0.0', $ip] : [];
        }
        $this->is_forwarded_valid = false;
        throw new Conflicting_Headers_Exception(\sprintf('The request has both a trusted "%s" header and a trusted "%s" header, conflicting with each other. You should either configure your proxy to remove one of them, or configure your project to distrust the offending one.', self::TRUSTED_HEADERS[self::HEADER_FORWARDED], self::TRUSTED_HEADERS[$type]));
    }
    private function normalize_and_filter_client_ips(array $client_ips, string $ip): array
    {
        if (!$client_ips) {
            return [];
        }
        $client_ips[] = $ip;
        // Complete the IP chain with the IP the request actually came from
        $first_trusted_ip = null;
        foreach ($client_ips as $key => $client_ip) {
            if (strpos((string) $client_ip, '.')) {
                // Strip :port from IPv4 addresses. This is allowed in Forwarded
                // and may occur in X-Forwarded-For.
                $i = strpos((string) $client_ip, ':');
                if ($i) {
                    $client_ips[$key] = $client_ip = substr((string) $client_ip, 0, $i);
                }
            } elseif (str_starts_with((string) $client_ip, '[')) {
                // Strip brackets and :port from IPv6 addresses.
                $i = strpos((string) $client_ip, ']', 1);
                $client_ips[$key] = $client_ip = substr((string) $client_ip, 1, $i - 1);
            }
            if (!filter_var($client_ip, \FILTER_VALIDATE_IP)) {
                unset($client_ips[$key]);
                continue;
            }
            if (Ip_Utils::check_ip($client_ip, self::$trusted_proxies)) {
                unset($client_ips[$key]);
                // Fallback to this when the client IP falls into the range of trusted proxies
                $first_trusted_ip ??= $client_ip;
            }
        }
        // Now the IP chain contains only untrusted proxies and the client IP
        return $client_ips ? array_reverse($client_ips) : [$first_trusted_ip];
    }
    /**
     * Is this IIS with UrlRewriteModule?
     *
     * This method consumes, caches and removed the IIS_WasUrlRewritten env var,
     * so we don't inherit it to sub-requests.
     */
    private function is_iis_rewrite(): bool
    {
        if (1 === $this->server->get_int('IIS_WasUrlRewritten')) {
            $this->is_iis_rewrite = true;
            $this->server->remove('IIS_WasUrlRewritten');
        }
        return $this->is_iis_rewrite;
    }
    /**
     * See https://url.spec.whatwg.org/.
     */
    private static function is_host_valid(string $host): bool
    {
        if ('[' === $host[0]) {
            return ']' === $host[-1] && filter_var(substr($host, 1, -1), \FILTER_VALIDATE_IP, \FILTER_FLAG_IPV6);
        }
        if (preg_match('/\.[0-9]++\.?$/D', $host)) {
            return null !== filter_var($host, \FILTER_VALIDATE_IP, \FILTER_FLAG_IPV4 | \FILTER_NULL_ON_FAILURE);
        }
        // use preg_replace() instead of preg_match() to prevent DoS attacks with long host names
        return '' === preg_replace('/[-a-zA-Z0-9_]++\.?/', '', $host);
    }
    private static function set_property(self $response, string $name, mixed $value): void
    {
        static $cache;
        $r = $cache[$name] ??= new \ReflectionProperty(self::class, $name);
        $r->set_raw_value($response, $value);
    }
}