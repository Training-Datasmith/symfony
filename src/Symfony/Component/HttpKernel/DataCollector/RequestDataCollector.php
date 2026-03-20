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
namespace Symfony\Component\Http_Kernel\Data_Collector;

use Symfony\Component\Event_Dispatcher\Event_Subscriber_Interface;
use Symfony\Component\Http_Foundation\Cookie;
use Symfony\Component\Http_Foundation\Parameter_Bag;
use Symfony\Component\Http_Foundation\Request;
use Symfony\Component\Http_Foundation\Request_Stack;
use Symfony\Component\Http_Foundation\Response;
use Symfony\Component\Http_Foundation\Session\Session_Bag_Interface;
use Symfony\Component\Http_Foundation\Session\Session_Interface;
use Symfony\Component\Http_Kernel\Event\Controller_Event;
use Symfony\Component\Http_Kernel\Event\Response_Event;
use Symfony\Component\Http_Kernel\Kernel_Events;
use Symfony\Component\Process\Process;
use Symfony\Component\Var_Dumper\Cloner\Data;
/**
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @final
 */
class Request_Data_Collector extends Data_Collector implements Event_Subscriber_Interface, Late_Data_Collector_Interface
{
    /**
     * @var \SplObjectStorage<Request, callable>
     */
    private \Spl_Object_Storage $controllers;
    private array $session_usages = [];
    public function __construct(private readonly ?Request_Stack $request_stack = null)
    {
        $this->controllers = new \Spl_Object_Storage();
    }
    public function collect(Request $request, Response $response, ?\Throwable $exception = null): void
    {
        // attributes are serialized and as they can be anything, they need to be converted to strings.
        $attributes = [];
        $route = '';
        foreach ($request->attributes->all() as $key => $value) {
            if ('_route' === $key) {
                $route = \is_object($value) ? $value->get_path() : $value;
                $attributes[$key] = $route;
            } else {
                $attributes[$key] = $value;
            }
        }
        $content = $request->get_content();
        $session_metadata = [];
        $session_attributes = [];
        $flashes = [];
        if (!$request->attributes->get_boolean('_stateless') && $request->has_session()) {
            $session = $request->get_session();
            if ($session->is_started()) {
                $session_metadata['Created'] = date(\DATE_RFC822, $session->get_metadata_bag()->get_created());
                $session_metadata['Last used'] = date(\DATE_RFC822, $session->get_metadata_bag()->get_last_used());
                $session_metadata['Lifetime'] = $session->get_metadata_bag()->get_lifetime();
                $session_attributes = $session->all();
                $flashes = $session->get_flash_bag()->peek_all();
            }
        }
        $status_code = $response->get_status_code();
        $response_cookies = [];
        foreach ($response->headers->get_cookies() as $cookie) {
            $response_cookies[$cookie->get_name()] = $cookie;
        }
        $dotenv_vars = [];
        foreach (explode(',', (string) ($_SERVER['SYMFONY_DOTENV_VARS'] ?? $_ENV['SYMFONY_DOTENV_VARS'] ?? '')) as $name) {
            if ('' !== $name && isset($_ENV[$name])) {
                $dotenv_vars[$name] = $_ENV[$name];
            }
        }
        $this->data = ['method' => $request->get_method(), 'format' => $request->get_request_format(), 'content_type' => $response->headers->get('Content-Type', 'text/html'), 'status_text' => Response::$status_texts[$status_code] ?? '', 'status_code' => $status_code, 'request_query' => $request->query->all(), 'request_request' => $request->request->all(), 'request_files' => $request->files->all(), 'request_headers' => $request->headers->all(), 'request_server' => $request->server->all(), 'request_cookies' => $request->cookies->all(), 'request_attributes' => $attributes, 'route' => $route, 'response_headers' => $response->headers->all(), 'response_cookies' => $response_cookies, 'session_metadata' => $session_metadata, 'session_attributes' => $session_attributes, 'session_usages' => array_values($this->session_usages), 'stateless_check' => $this->request_stack?->get_main_request()?->attributes->get('_stateless') ?? false, 'flashes' => $flashes, 'path_info' => $request->get_path_info(), 'controller' => 'n/a', 'locale' => $request->get_locale(), 'dotenv_vars' => $dotenv_vars];
        if (isset($this->data['request_headers']['php-auth-pw'])) {
            $this->data['request_headers']['php-auth-pw'] = '******';
        }
        if (isset($this->data['request_server']['PHP_AUTH_PW'])) {
            $this->data['request_server']['PHP_AUTH_PW'] = '******';
        }
        if (isset($this->data['request_request']['_password'])) {
            $encoded_password = rawurlencode((string) $this->data['request_request']['_password']);
            $content = str_replace('_password=' . $encoded_password, '_password=******', $content);
            $this->data['request_request']['_password'] = '******';
        }
        $this->data['content'] = $content;
        $this->data['curlCommand'] = $this->compute_curl_command($request, $content);
        foreach ($this->data as $key => $value) {
            if (!\is_array($value)) {
                continue;
            }
            if ('request_headers' === $key || 'response_headers' === $key) {
                $this->data[$key] = array_map(static fn(array $v) => isset($v[0]) && !isset($v[1]) ? $v[0] : $v, $value);
            }
        }
        if (isset($this->controllers[$request])) {
            $this->data['controller'] = $this->parse_controller($this->controllers[$request]);
            unset($this->controllers[$request]);
        }
        if ($request->attributes->has('_redirected') && $redirect_cookie = $request->cookies->get('sf_redirect')) {
            $this->data['redirect'] = json_decode($redirect_cookie, true);
            $response->headers->clear_cookie('sf_redirect');
        }
        if ($response->is_redirect()) {
            $response->headers->set_cookie(new Cookie('sf_redirect', json_encode(['token' => $response->headers->get('x-debug-token'), 'route' => $request->attributes->get('_route', 'n/a'), 'method' => $request->get_method(), 'controller' => $this->parse_controller($request->attributes->get('_controller')), 'status_code' => $status_code, 'status_text' => Response::$status_texts[$status_code]]), 0, '/', null, $request->is_secure(), true, false, 'lax'));
        }
        $this->data['identifier'] = $this->data['route'] ?: (\is_array($this->data['controller']) ? $this->data['controller']['class'] . '::' . $this->data['controller']['method'] . '()' : $this->data['controller']);
        if ($response->headers->has('x-previous-debug-token')) {
            $this->data['forward_token'] = $response->headers->get('x-previous-debug-token');
        }
    }
    public function late_collect(): void
    {
        $this->data = $this->clone_var($this->data);
    }
    public function reset(): void
    {
        parent::reset();
        $this->controllers = new \Spl_Object_Storage();
        $this->session_usages = [];
    }
    public function get_method(): string
    {
        return $this->data['method'];
    }
    public function get_path_info(): string
    {
        return $this->data['path_info'];
    }
    public function get_request_request(): Parameter_Bag
    {
        return new Parameter_Bag($this->data['request_request']->get_value());
    }
    public function get_request_query(): Parameter_Bag
    {
        return new Parameter_Bag($this->data['request_query']->get_value());
    }
    public function get_request_files(): Parameter_Bag
    {
        return new Parameter_Bag($this->data['request_files']->get_value());
    }
    public function get_request_headers(): Parameter_Bag
    {
        return new Parameter_Bag($this->data['request_headers']->get_value());
    }
    public function get_request_server(bool $raw = false): Parameter_Bag
    {
        return new Parameter_Bag($this->data['request_server']->get_value($raw));
    }
    public function get_request_cookies(bool $raw = false): Parameter_Bag
    {
        return new Parameter_Bag($this->data['request_cookies']->get_value($raw));
    }
    public function get_request_attributes(): Parameter_Bag
    {
        return new Parameter_Bag($this->data['request_attributes']->get_value());
    }
    public function get_response_headers(): Parameter_Bag
    {
        return new Parameter_Bag($this->data['response_headers']->get_value());
    }
    public function get_response_cookies(): Parameter_Bag
    {
        return new Parameter_Bag($this->data['response_cookies']->get_value());
    }
    public function get_session_metadata(): array
    {
        return $this->data['session_metadata']->get_value();
    }
    public function get_session_attributes(): array
    {
        return $this->data['session_attributes']->get_value();
    }
    public function get_stateless_check(): bool
    {
        return $this->data['stateless_check'];
    }
    public function get_session_usages(): Data|array
    {
        return $this->data['session_usages'];
    }
    public function get_flashes(): array
    {
        return $this->data['flashes']->get_value();
    }
    /**
     * @return string|resource
     */
    public function get_content()
    {
        return $this->data['content'];
    }
    public function is_json_request(): bool
    {
        return 1 === preg_match('{^application/(?:\w+\++)*json$}i', (string) $this->data['request_headers']['content-type']);
    }
    public function get_pretty_json(): ?string
    {
        $decoded = json_decode($this->get_content());
        return \JSON_ERROR_NONE === json_last_error() ? json_encode($decoded, \JSON_PRETTY_PRINT) : null;
    }
    public function get_content_type(): string
    {
        return $this->data['content_type'];
    }
    public function get_status_text(): string
    {
        return $this->data['status_text'];
    }
    public function get_status_code(): int
    {
        return $this->data['status_code'];
    }
    public function get_format(): string
    {
        return $this->data['format'];
    }
    public function get_locale(): string
    {
        return $this->data['locale'];
    }
    public function get_dotenv_vars(): Parameter_Bag
    {
        return new Parameter_Bag($this->data['dotenv_vars']->get_value());
    }
    /**
     * Gets the route name.
     *
     * The _route request attributes is automatically set by the Router Matcher.
     */
    public function get_route(): string
    {
        return $this->data['route'];
    }
    public function get_identifier(): string
    {
        return $this->data['identifier'];
    }
    /**
     * Gets the route parameters.
     *
     * The _route_params request attributes is automatically set by the RouterListener.
     */
    public function get_route_params(): array
    {
        return isset($this->data['request_attributes']['_route_params']) ? $this->data['request_attributes']['_route_params']->get_value() : [];
    }
    /**
     * Gets the parsed controller.
     *
     * @return array|string|Data The controller as a string or array of data
     *                           with keys 'class', 'method', 'file' and 'line'
     */
    public function get_controller(): array|string|Data
    {
        return $this->data['controller'];
    }
    /**
     * Gets the previous request attributes.
     *
     * @return array|Data|false A legacy array of data from the previous redirection response
     *                          or false otherwise
     */
    public function get_redirect(): array|Data|false
    {
        return $this->data['redirect'] ?? false;
    }
    public function get_forward_token(): ?string
    {
        return $this->data['forward_token'] ?? null;
    }
    public function on_kernel_controller(Controller_Event $event): void
    {
        $this->controllers[$event->get_request()] = $event->get_controller();
    }
    public function on_kernel_response(Response_Event $event): void
    {
        if (!$event->is_main_request()) {
            return;
        }
        if ($event->get_request()->cookies->has('sf_redirect')) {
            $event->get_request()->attributes->set('_redirected', true);
        }
    }
    public static function get_subscribed_events(): array
    {
        return [Kernel_Events::CONTROLLER => 'onKernelController', Kernel_Events::RESPONSE => 'onKernelResponse'];
    }
    public function get_name(): string
    {
        return 'request';
    }
    public function collect_session_usage(): void
    {
        $trace = debug_backtrace(\DEBUG_BACKTRACE_IGNORE_ARGS);
        $trace_end_index = \count($trace) - 1;
        for ($i = $trace_end_index; $i > 0; --$i) {
            if (null !== ($class = $trace[$i]['class'] ?? null) && (is_subclass_of($class, Session_Interface::class) || is_subclass_of($class, Session_Bag_Interface::class))) {
                $trace_end_index = $i;
                break;
            }
        }
        if (\count($trace) - 1 === $trace_end_index) {
            return;
        }
        // Remove part of the backtrace that belongs to session only
        array_splice($trace, 0, $trace_end_index);
        // Merge identical backtraces generated by internal call reports
        $name = \sprintf('%s:%s', $trace[1]['class'] ?? $trace[0]['file'], $trace[0]['line']);
        if (!\array_key_exists($name, $this->session_usages)) {
            $this->session_usages[$name] = ['name' => $name, 'file' => $trace[0]['file'], 'line' => $trace[0]['line'], 'trace' => $trace];
        }
    }
    /**
     * @return array|string An array of controller data or a simple string
     */
    private function parse_controller(array|object|string|null $controller): array|string
    {
        if (\is_string($controller) && str_contains($controller, '::')) {
            $controller = explode('::', $controller);
        }
        if (\is_array($controller)) {
            try {
                $r = new \ReflectionMethod($controller[0], $controller[1]);
                return ['class' => \is_object($controller[0]) ? get_debug_type($controller[0]) : $controller[0], 'method' => $controller[1], 'file' => $r->get_file_name(), 'line' => $r->get_start_line()];
            } catch (\Reflection_Exception) {
                if (\is_callable($controller)) {
                    // using __call or  __callStatic
                    return ['class' => \is_object($controller[0]) ? get_debug_type($controller[0]) : $controller[0], 'method' => $controller[1], 'file' => 'n/a', 'line' => 'n/a'];
                }
            }
        }
        if ($controller instanceof \Closure) {
            $r = new \ReflectionFunction($controller);
            $controller = ['class' => $r->get_name(), 'method' => null, 'file' => $r->get_file_name(), 'line' => $r->get_start_line()];
            if ($r->is_anonymous()) {
                return $controller;
            }
            $controller['method'] = $r->name;
            if ($class = $r->get_closure_called_class()) {
                $controller['class'] = $class->name;
            } else {
                return $r->name;
            }
            return $controller;
        }
        if (\is_object($controller)) {
            $r = new \ReflectionClass($controller);
            return ['class' => $r->get_name(), 'method' => null, 'file' => $r->get_file_name(), 'line' => $r->get_start_line()];
        }
        return \is_string($controller) ? $controller : 'n/a';
    }
    private function compute_curl_command(Request $request, ?string $content): string
    {
        $command = ['curl', '--compressed'];
        $method = $request->get_method();
        if (Request::METHOD_HEAD === $method) {
            $command[] = '--head';
        } elseif (Request::METHOD_GET !== $method) {
            $command[] = \sprintf('--request %s', $method);
        }
        $command[] = \sprintf('--url %s', escapeshellarg($request->get_uri()));
        foreach ($request->headers->all() as $name => $values) {
            if (\in_array(strtolower($name), ['host', 'cookie'], true)) {
                continue;
            }
            $command[] = '--header ' . escapeshellarg(ucwords($name, '-') . ': ' . implode(', ', $values));
        }
        if ($request->cookies->all()) {
            $cookies = [];
            foreach ($request->cookies->all() as $name => $value) {
                $cookies[] = urlencode($name) . '=' . urlencode((string) $value);
            }
            $command[] = '--cookie ' . escapeshellarg(implode('; ', $cookies));
        }
        if ($content && \in_array($method, [Request::METHOD_POST, Request::METHOD_PUT, Request::METHOD_PATCH, Request::METHOD_DELETE], true)) {
            $command[] = '--data-raw ' . $this->escape_payload($content);
        }
        return implode(" \\\n  ", $command);
    }
    public function get_curl_command(): string
    {
        return $this->data['curlCommand'] ?? '';
    }
    private function escape_payload(string $payload): string
    {
        static $use_process;
        if ($use_process ??= \function_exists('proc_open') && class_exists(Process::class)) {
            return substr((new Process(['', $payload]))->get_command_line(), 3);
        }
        if ('\\' === \DIRECTORY_SEPARATOR) {
            return '"' . str_replace('"', '""', $payload) . '"';
        }
        return "'" . str_replace("'", "'\\''", $payload) . "'";
    }
}