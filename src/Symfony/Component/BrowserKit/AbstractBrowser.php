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

use Symfony\Component\Browser_Kit\Exception\BadMethodCallException;
use Symfony\Component\Browser_Kit\Exception\InvalidArgumentException;
use Symfony\Component\Browser_Kit\Exception\LogicException;
use Symfony\Component\Browser_Kit\Exception\RuntimeException;
use Symfony\Component\Dom_Crawler\Crawler;
use Symfony\Component\Dom_Crawler\Form;
use Symfony\Component\Dom_Crawler\Link;
use Symfony\Component\Process\Php_Process;
use Symfony\Component\Process\Process;
/**
 * Simulates a browser.
 *
 * To make the actual request, you need to implement the doRequest() method.
 *
 * If you want to be able to run requests in their own process (insulated flag),
 * you need to also implement the getScript() method.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @template TRequest of object
 * @template TResponse of object
 */
abstract class Abstract_Browser
{
    protected History $history;
    protected Cookie_Jar $cookie_jar;
    protected array $server = [];
    protected Request $internal_request;
    /** @psalm-var TRequest */
    protected object $request;
    protected Response $internal_response;
    /** @psalm-var TResponse */
    protected object $response;
    protected Crawler $crawler;
    protected string|false $wrap_content_pattern = false;
    protected bool $insulated = false;
    protected ?string $redirect = null;
    protected bool $follow_redirects = true;
    protected bool $follow_meta_refresh = false;
    private int $max_redirects = -1;
    private int $redirect_count = 0;
    private array $redirects = [];
    private bool $is_main_request = true;
    /**
     * @param array $server The server parameters (equivalent of $_SERVER)
     */
    public function __construct(array $server = [], ?History $history = null, ?Cookie_Jar $cookie_jar = null)
    {
        $this->set_server_parameters($server);
        $this->history = $history ?? new History();
        $this->cookie_jar = $cookie_jar ?? new Cookie_Jar();
    }
    /**
     * Sets whether to automatically follow redirects or not.
     */
    public function follow_redirects(bool $follow_redirects = true): void
    {
        $this->follow_redirects = $follow_redirects;
    }
    /**
     * Sets whether to automatically follow meta refresh redirects or not.
     */
    public function follow_meta_refresh(bool $follow_meta_refresh = true): void
    {
        $this->follow_meta_refresh = $follow_meta_refresh;
    }
    /**
     * Returns whether client automatically follows redirects or not.
     */
    public function is_following_redirects(): bool
    {
        return $this->follow_redirects;
    }
    /**
     * Sets the maximum number of redirects that crawler can follow.
     */
    public function set_max_redirects(int $max_redirects): void
    {
        $this->max_redirects = $max_redirects < 0 ? -1 : $max_redirects;
        $this->follow_redirects = -1 !== $this->max_redirects;
    }
    /**
     * Returns the maximum number of redirects that crawler can follow.
     */
    public function get_max_redirects(): int
    {
        return $this->max_redirects;
    }
    /**
     * Sets the insulated flag.
     *
     * @throws LogicException When Symfony Process Component is not installed
     */
    public function insulate(bool $insulated = true): void
    {
        if ($insulated && !class_exists(Process::class)) {
            throw new LogicException('Unable to isolate requests as the Symfony Process Component is not installed. Try running "composer require symfony/process".');
        }
        $this->insulated = $insulated;
    }
    /**
     * Sets server parameters.
     */
    public function set_server_parameters(array $server): void
    {
        $this->server = array_merge(['HTTP_USER_AGENT' => 'Symfony BrowserKit'], $server);
    }
    /**
     * Sets single server parameter.
     */
    public function set_server_parameter(string $key, string $value): void
    {
        $this->server[$key] = $value;
    }
    /**
     * Gets single server parameter for specified key.
     */
    public function get_server_parameter(string $key, mixed $default = ''): mixed
    {
        return $this->server[$key] ?? $default;
    }
    public function xml_http_request(string $method, string $uri, array $parameters = [], array $files = [], array $server = [], ?string $content = null, bool $change_history = true): Crawler
    {
        $this->set_server_parameter('HTTP_X_REQUESTED_WITH', 'XMLHttpRequest');
        try {
            return $this->request($method, $uri, $parameters, $files, $server, $content, $change_history);
        } finally {
            unset($this->server['HTTP_X_REQUESTED_WITH']);
        }
    }
    /**
     * Converts the request parameters into a JSON string and uses it as request content.
     */
    public function json_request(string $method, string $uri, array $parameters = [], array $server = [], bool $change_history = true): Crawler
    {
        $content = json_encode($parameters, \JSON_PRESERVE_ZERO_FRACTION);
        $this->set_server_parameter('CONTENT_TYPE', 'application/json');
        $this->set_server_parameter('HTTP_ACCEPT', 'application/json');
        try {
            return $this->request($method, $uri, [], [], $server, $content, $change_history);
        } finally {
            unset($this->server['CONTENT_TYPE']);
            unset($this->server['HTTP_ACCEPT']);
        }
    }
    /**
     * Returns the History instance.
     */
    public function get_history(): History
    {
        return $this->history;
    }
    /**
     * Returns the CookieJar instance.
     */
    public function get_cookie_jar(): Cookie_Jar
    {
        return $this->cookie_jar;
    }
    /**
     * Returns the current Crawler instance.
     */
    public function get_crawler(): Crawler
    {
        return $this->crawler ?? throw new BadMethodCallException(\sprintf('The "request()" method must be called before "%s()".', __METHOD__));
    }
    /**
     * Sets the content wrapper format.
     *
     * @example <table>%s</table>
     */
    public function wrap_content(false|string $pattern): void
    {
        $this->wrap_content_pattern = $pattern;
    }
    /**
     * Returns the current BrowserKit Response instance.
     */
    public function get_internal_response(): Response
    {
        return $this->internal_response ?? throw new BadMethodCallException(\sprintf('The "request()" method must be called before "%s()".', __METHOD__));
    }
    /**
     * Returns the current origin response instance.
     *
     * The origin response is the response instance that is returned
     * by the code that handles requests.
     *
     * @psalm-return TResponse
     *
     * @see doRequest()
     */
    public function get_response(): object
    {
        return $this->response ?? throw new BadMethodCallException(\sprintf('The "request()" method must be called before "%s()".', __METHOD__));
    }
    /**
     * Returns the current BrowserKit Request instance.
     */
    public function get_internal_request(): Request
    {
        return $this->internal_request ?? throw new BadMethodCallException(\sprintf('The "request()" method must be called before "%s()".', __METHOD__));
    }
    /**
     * Returns the current origin Request instance.
     *
     * The origin request is the request instance that is sent
     * to the code that handles requests.
     *
     * @psalm-return TRequest
     *
     * @see doRequest()
     */
    public function get_request(): object
    {
        return $this->request ?? throw new BadMethodCallException(\sprintf('The "request()" method must be called before "%s()".', __METHOD__));
    }
    /**
     * Clicks on a given link.
     *
     * @param array $serverParameters An array of server parameters
     */
    public function click(Link $link, array $server_parameters = []): Crawler
    {
        if ($link instanceof Form) {
            return $this->submit($link, [], $server_parameters);
        }
        return $this->request($link->get_method(), $link->get_uri(), [], [], $server_parameters);
    }
    /**
     * Clicks the first link (or clickable image) that contains the given text.
     *
     * @param string $linkText         The text of the link or the alt attribute of the clickable image
     * @param array  $serverParameters An array of server parameters
     */
    public function click_link(string $link_text, array $server_parameters = []): Crawler
    {
        $crawler = $this->crawler ?? throw new BadMethodCallException(\sprintf('The "request()" method must be called before "%s()".', __METHOD__));
        return $this->click($crawler->select_link($link_text)->link(), $server_parameters);
    }
    /**
     * Submits a form.
     *
     * @param array $values           An array of form field values
     * @param array $serverParameters An array of server parameters
     */
    public function submit(Form $form, array $values = [], array $server_parameters = []): Crawler
    {
        $form->set_values($values);
        return $this->request($form->get_method(), $form->get_uri(), $form->get_php_values(), $form->get_php_files(), $server_parameters);
    }
    /**
     * Finds the first form that contains a button with the given content and
     * uses it to submit the given form field values.
     *
     * @param string $button           The text content, id, value or name of the form <button> or <input type="submit">
     * @param array  $fieldValues      Use this syntax: ['my_form[name]' => '...', 'my_form[email]' => '...']
     * @param string $method           The HTTP method used to submit the form
     * @param array  $serverParameters These values override the ones stored in $_SERVER (HTTP headers must include an HTTP_ prefix as PHP does)
     */
    public function submit_form(string $button, array $field_values = [], string $method = 'POST', array $server_parameters = []): Crawler
    {
        $crawler = $this->crawler ?? throw new BadMethodCallException(\sprintf('The "request()" method must be called before "%s()".', __METHOD__));
        $button_node = $crawler->select_button($button);
        if (0 === $button_node->count()) {
            throw new InvalidArgumentException(\sprintf('There is no button with "%s" as its content, id, value or name.', $button));
        }
        $form = $button_node->form($field_values, $method);
        return $this->submit($form, [], $server_parameters);
    }
    /**
     * Calls a URI.
     *
     * @param string $method        The request method
     * @param string $uri           The URI to fetch
     * @param array  $parameters    The Request parameters
     * @param array  $files         The files
     * @param array  $server        The server parameters (HTTP headers are referenced with an HTTP_ prefix as PHP does)
     * @param string $content       The raw body data
     * @param bool   $changeHistory Whether to update the history or not (only used internally for back(), forward(), and reload())
     */
    public function request(string $method, string $uri, array $parameters = [], array $files = [], array $server = [], ?string $content = null, bool $change_history = true): Crawler
    {
        if ($this->is_main_request) {
            $this->redirect_count = 0;
        } else {
            ++$this->redirect_count;
        }
        $original_uri = $uri;
        $uri = $this->get_absolute_uri($uri);
        $server = array_merge($this->server, $server);
        if (!empty($server['HTTP_HOST']) && !parse_url($original_uri, \PHP_URL_HOST)) {
            $uri = preg_replace('{^(https?\://)' . preg_quote((string) $this->extract_host($uri)) . '}', '${1}' . $server['HTTP_HOST'], $uri);
        }
        if (isset($server['HTTPS']) && !parse_url($original_uri, \PHP_URL_SCHEME)) {
            $uri = preg_replace('{^' . parse_url((string) $uri, \PHP_URL_SCHEME) . '}', $server['HTTPS'] ? 'https' : 'http', (string) $uri);
        }
        if (!isset($server['HTTP_REFERER']) && !$this->history->is_empty()) {
            $server['HTTP_REFERER'] = $this->history->current()->get_uri();
        }
        if (empty($server['HTTP_HOST'])) {
            $server['HTTP_HOST'] = $this->extract_host($uri);
        }
        $server['HTTPS'] = 'https' === parse_url((string) $uri, \PHP_URL_SCHEME);
        $this->internal_request = new Request($uri, $method, $parameters, $files, $this->cookie_jar->all_values($uri), $server, $content);
        $this->request = $this->filter_request($this->internal_request);
        if (true === $change_history) {
            $this->history->add($this->internal_request);
        }
        if ($this->insulated) {
            $this->response = $this->do_request_in_process($this->request);
        } else {
            $this->response = $this->do_request($this->request);
        }
        $this->internal_response = $this->filter_response($this->response);
        $this->cookie_jar->update_from_response($this->internal_response, $uri);
        $status = $this->internal_response->get_status_code();
        if ($status >= 300 && $status < 400) {
            $this->redirect = $this->internal_response->get_header('Location');
        } else {
            $this->redirect = null;
        }
        if ($this->follow_redirects && $this->redirect) {
            $this->redirects[serialize($this->history->current())] = true;
            return $this->crawler = $this->follow_redirect();
        }
        $response_content = $this->internal_response->get_content();
        if ($this->wrap_content_pattern) {
            $response_content = \sprintf($this->wrap_content_pattern, $response_content);
        }
        $this->crawler = $this->create_crawler_from_content($this->internal_request->get_uri(), $response_content, $this->internal_response->get_header('Content-Type') ?? '');
        // Check for meta refresh redirect
        if ($this->follow_meta_refresh && null !== $redirect = $this->get_meta_refresh_url()) {
            $this->redirect = $redirect;
            $this->redirects[serialize($this->history->current())] = true;
            $this->crawler = $this->follow_redirect();
        }
        return $this->crawler;
    }
    /**
     * Makes a request in another process.
     *
     * @psalm-param TRequest $request
     *
     * @psalm-return TResponse
     *
     * @throws \RuntimeException When processing returns exit code
     */
    protected function do_request_in_process(object $request): object
    {
        $deprecations_file = tempnam(sys_get_temp_dir(), 'deprec');
        putenv('SYMFONY_DEPRECATIONS_SERIALIZE=' . $deprecations_file);
        $_ENV['SYMFONY_DEPRECATIONS_SERIALIZE'] = $deprecations_file;
        $process = new Php_Process($this->get_script($request));
        $process->run();
        if (file_exists($deprecations_file)) {
            $deprecations = file_get_contents($deprecations_file);
            unlink($deprecations_file);
            foreach ($deprecations ? unserialize($deprecations) : [] as $deprecation) {
                if ($deprecation[0]) {
                    // unsilenced on purpose
                    trigger_error($deprecation[1], \E_USER_DEPRECATED);
                } else {
                    @trigger_error($deprecation[1], \E_USER_DEPRECATED);
                }
            }
        }
        if (!$process->is_successful() || !preg_match('/^O\:\d+\:/', $process->get_output())) {
            throw new RuntimeException(\sprintf('OUTPUT: %s ERROR OUTPUT: %s.', $process->get_output(), $process->get_error_output()));
        }
        return unserialize($process->get_output());
    }
    /**
     * Makes a request.
     *
     * @psalm-param TRequest $request
     *
     * @psalm-return TResponse
     */
    abstract protected function do_request(object $request): object;
    /**
     * Returns the script to execute when the request must be insulated.
     *
     * @param object $request An origin request instance
     *
     * @psalm-param TRequest $request
     *
     * @throws LogicException When this abstract class is not implemented
     */
    protected function get_script(object $request): string
    {
        throw new LogicException('To insulate requests, you need to override the getScript() method.');
    }
    /**
     * Filters the BrowserKit request to the origin one.
     *
     * @psalm-return TRequest
     */
    protected function filter_request(Request $request): object
    {
        return $request;
    }
    /**
     * Filters the origin response to the BrowserKit one.
     *
     * @psalm-param TResponse $response
     */
    protected function filter_response(object $response): Response
    {
        return $response;
    }
    /**
     * Creates a crawler.
     *
     * This method returns null if the DomCrawler component is not available.
     */
    protected function create_crawler_from_content(string $uri, string $content, string $type): ?Crawler
    {
        if (!class_exists(Crawler::class)) {
            return null;
        }
        $crawler = new Crawler(null, $uri);
        $crawler->add_content($content, $type);
        return $crawler;
    }
    /**
     * Goes back in the browser history.
     */
    public function back(): Crawler
    {
        do {
            $request = $this->history->back();
        } while (\array_key_exists(serialize($request), $this->redirects));
        return $this->request_from_request($request, false);
    }
    /**
     * Goes forward in the browser history.
     */
    public function forward(): Crawler
    {
        do {
            $request = $this->history->forward();
        } while (\array_key_exists(serialize($request), $this->redirects));
        return $this->request_from_request($request, false);
    }
    /**
     * Reloads the current browser.
     */
    public function reload(): Crawler
    {
        return $this->request_from_request($this->history->current(), false);
    }
    /**
     * Follow redirects?
     *
     * @throws LogicException If request was not a redirect
     */
    public function follow_redirect(): Crawler
    {
        if (!isset($this->redirect)) {
            throw new LogicException('The request was not redirected.');
        }
        if (-1 !== $this->max_redirects) {
            if ($this->redirect_count > $this->max_redirects) {
                $this->redirect_count = 0;
                throw new LogicException(\sprintf('The maximum number (%d) of redirections was reached.', $this->max_redirects));
            }
        }
        $request = $this->internal_request;
        if (\in_array($this->internal_response->get_status_code(), [301, 302, 303], true)) {
            $method = 'GET';
            $files = [];
            $content = null;
        } else {
            $method = $request->get_method();
            $files = $request->get_files();
            $content = $request->get_content();
        }
        if ('GET' === strtoupper($method)) {
            // Don't forward parameters for GET request as it should reach the redirection URI
            $parameters = [];
        } else {
            $parameters = $request->get_parameters();
        }
        $server = $request->get_server();
        $server = $this->update_server_from_uri($server, $this->redirect);
        $this->is_main_request = false;
        $response = $this->request($method, $this->redirect, $parameters, $files, $server, $content);
        $this->is_main_request = true;
        return $response;
    }
    /**
     * @see https://dev.w3.org/html5/spec-preview/the-meta-element.html#attr-meta-http-equiv-refresh
     */
    private function get_meta_refresh_url(): ?string
    {
        $meta_refresh = $this->get_crawler()->filter('head meta[http-equiv="refresh"]');
        foreach ($meta_refresh->extract(['content']) as $content) {
            if (preg_match('/^\s*0\s*;\s*URL\s*=\s*(?|\'([^\']++)|"([^"]++)|([^\'"].*))/i', (string) $content, $m)) {
                return str_replace("\t\r\n", '', rtrim($m[1]));
            }
        }
        return null;
    }
    /**
     * Restarts the client.
     *
     * It flushes history and all cookies.
     */
    public function restart(): void
    {
        $this->cookie_jar->clear();
        $this->history->clear();
    }
    /**
     * Takes a URI and converts it to absolute if it is not already absolute.
     */
    protected function get_absolute_uri(string $uri): string
    {
        // already absolute?
        if (str_starts_with($uri, 'http://') || str_starts_with($uri, 'https://')) {
            return $uri;
        }
        if (!$this->history->is_empty()) {
            $current_uri = $this->history->current()->get_uri();
        } else {
            $current_uri = \sprintf('http%s://%s/', isset($this->server['HTTPS']) ? 's' : '', $this->server['HTTP_HOST'] ?? 'localhost');
        }
        // protocol relative URL
        if ('' !== trim($uri, '/') && str_starts_with($uri, '//')) {
            return parse_url($current_uri, \PHP_URL_SCHEME) . ':' . $uri;
        }
        // anchor or query string parameters?
        if (!$uri || '#' === $uri[0] || '?' === $uri[0]) {
            return preg_replace('/[#?].*?$/', '', $current_uri) . $uri;
        }
        if ('/' !== $uri[0]) {
            $path = parse_url($current_uri, \PHP_URL_PATH);
            if (!str_ends_with($path, '/')) {
                $path = substr($path, 0, strrpos($path, '/') + 1);
            }
            $uri = $path . $uri;
        }
        return preg_replace('#^(.*?//[^/?]+)[/?].*$#', '$1', $current_uri) . $uri;
    }
    /**
     * Makes a request from a Request object directly.
     *
     * @param bool $changeHistory Whether to update the history or not (only used internally for back(), forward(), and reload())
     */
    protected function request_from_request(Request $request, bool $change_history = true): Crawler
    {
        return $this->request($request->get_method(), $request->get_uri(), $request->get_parameters(), $request->get_files(), $request->get_server(), $request->get_content(), $change_history);
    }
    private function update_server_from_uri(array $server, string $uri): array
    {
        $server['HTTP_HOST'] = $this->extract_host($uri);
        $scheme = parse_url($uri, \PHP_URL_SCHEME);
        $server['HTTPS'] = null === $scheme ? $server['HTTPS'] : 'https' === $scheme;
        unset($server['HTTP_IF_NONE_MATCH'], $server['HTTP_IF_MODIFIED_SINCE']);
        return $server;
    }
    private function extract_host(string $uri): ?string
    {
        $host = parse_url($uri, \PHP_URL_HOST);
        if ($port = parse_url($uri, \PHP_URL_PORT)) {
            return $host . ':' . $port;
        }
        return $host;
    }
}