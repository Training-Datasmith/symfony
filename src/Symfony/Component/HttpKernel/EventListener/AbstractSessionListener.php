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
namespace Symfony\Component\Http_Kernel\Event_Listener;

use Psr\Container\Container_Interface;
use Symfony\Component\Event_Dispatcher\Event_Subscriber_Interface;
use Symfony\Component\Http_Foundation\Cookie;
use Symfony\Component\Http_Foundation\Session\Session;
use Symfony\Component\Http_Foundation\Session\Session_Interface;
use Symfony\Component\Http_Foundation\Session\Session_Utils;
use Symfony\Component\Http_Kernel\Event\Request_Event;
use Symfony\Component\Http_Kernel\Event\Response_Event;
use Symfony\Component\Http_Kernel\Exception\Unexpected_Session_Usage_Exception;
use Symfony\Component\Http_Kernel\Kernel_Events;
use Symfony\Contracts\Service\Reset_Interface;
/**
 * Sets the session onto the request on the "kernel.request" event and saves
 * it on the "kernel.response" event.
 *
 * In addition, if the session has been started it overrides the Cache-Control
 * header in such a way that all caching is disabled in that case.
 * If you have a scenario where caching responses with session information in
 * them makes sense, you can disable this behaviour by setting the header
 * AbstractSessionListener::NO_AUTO_CACHE_CONTROL_HEADER on the response.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 * @author Tobias Schultze <http://tobion.de>
 */
abstract class Abstract_Session_Listener implements Event_Subscriber_Interface, Reset_Interface
{
    public const NO_AUTO_CACHE_CONTROL_HEADER = 'Symfony-Session-NoAutoCacheControl';
    /**
     * @param array<string, mixed> $sessionOptions
     *
     * @internal
     */
    public function __construct(private readonly ?Container_Interface $container = null, private readonly bool $debug = false, private readonly array $session_options = [])
    {
    }
    /**
     * @internal
     */
    public function on_kernel_request(Request_Event $event): void
    {
        if (!$event->is_main_request()) {
            return;
        }
        $request = $event->get_request();
        if (!$request->has_session()) {
            $request->set_session_factory(function () use ($request) {
                // Prevent calling `$this->getSession()` twice in case the Request (and the below factory) is cloned
                static $sess;
                if (!$sess) {
                    $sess = $this->get_session();
                    $request->set_session($sess);
                    /*
                     * For supporting sessions in php runtime with runners like roadrunner or swoole, the session
                     * cookie needs to be read from the cookie bag and set on the session storage.
                     *
                     * Do not set it when a native php session is active.
                     */
                    if ($sess && !$sess->is_started() && \PHP_SESSION_ACTIVE !== session_status()) {
                        $session_id = $sess->get_id() ?: $request->cookies->get($sess->get_name(), '');
                        $sess->set_id($session_id);
                    }
                }
                return $sess;
            });
        }
    }
    /**
     * @internal
     */
    public function on_kernel_response(Response_Event $event): void
    {
        if (!$event->is_main_request()) {
            return;
        }
        $response = $event->get_response();
        $auto_cache_control = !$response->headers->has(self::NO_AUTO_CACHE_CONTROL_HEADER);
        // Always remove the internal header if present
        $response->headers->remove(self::NO_AUTO_CACHE_CONTROL_HEADER);
        if (!$event->get_request()->has_session(true)) {
            return;
        }
        $session = $event->get_request()->get_session();
        if ($session->is_started()) {
            /*
             * Saves the session, in case it is still open, before sending the response/headers.
             *
             * This ensures several things in case the developer did not save the session explicitly:
             *
             *  * If a session save handler without locking is used, it ensures the data is available
             *    on the next request, e.g. after a redirect. PHPs auto-save at script end via
             *    session_register_shutdown is executed after fastcgi_finish_request. So in this case
             *    the data could be missing the next request because it might not be saved the moment
             *    the new request is processed.
             *  * A locking save handler (e.g. the native 'files') circumvents concurrency problems like
             *    the one above. But by saving the session before long-running things in the terminate event,
             *    we ensure the session is not blocked longer than needed.
             *  * When regenerating the session ID no locking is involved in PHPs session design. See
             *    https://bugs.php.net/61470 for a discussion. So in this case, the session must
             *    be saved anyway before sending the headers with the new session ID. Otherwise session
             *    data could get lost again for concurrent requests with the new ID. One result could be
             *    that you get logged out after just logging in.
             *
             * This listener should be executed as one of the last listeners, so that previous listeners
             * can still operate on the open session. This prevents the overhead of restarting it.
             * Listeners after closing the session can still work with the session as usual because
             * Symfonys session implementation starts the session on demand. So writing to it after
             * it is saved will just restart it.
             */
            $session->save();
            /*
             * For supporting sessions in php runtime with runners like roadrunner or swoole the session
             * cookie need to be written on the response object and should not be written by PHP itself.
             */
            $session_name = $session->get_name();
            $session_id = $session->get_id();
            $session_options = $this->get_session_options($this->session_options);
            $session_cookie_path = $session_options['cookie_path'] ?? '/';
            $session_cookie_domain = $session_options['cookie_domain'] ?? null;
            $session_cookie_secure = $session_options['cookie_secure'] ?? false;
            $session_cookie_http_only = $session_options['cookie_httponly'] ?? true;
            $session_cookie_same_site = $session_options['cookie_samesite'] ?? Cookie::SAMESITE_LAX;
            $session_use_cookies = $session_options['use_cookies'] ?? true;
            Session_Utils::pop_session_cookie($session_name, $session_id);
            if ($session_use_cookies) {
                $request = $event->get_request();
                $request_session_cookie_id = $request->cookies->get($session_name);
                $is_session_empty = ($session instanceof Session ? $session->is_empty() : !$session->all()) && empty($_SESSION);
                // checking $_SESSION to keep compatibility with native sessions
                if ($request_session_cookie_id && $is_session_empty) {
                    // PHP internally sets the session cookie value to "deleted" when setcookie() is called with empty string $value argument
                    // which happens in \Symfony\Component\HttpFoundation\Session\Storage\Handler\AbstractSessionHandler::destroy
                    // when the session gets invalidated (for example on logout) so we must handle this case here too
                    // otherwise we would send two Set-Cookie headers back with the response
                    Session_Utils::pop_session_cookie($session_name, 'deleted');
                    $response->headers->clear_cookie($session_name, $session_cookie_path, $session_cookie_domain, $session_cookie_secure, $session_cookie_http_only, $session_cookie_same_site);
                } elseif ($session_id !== $request_session_cookie_id && !$is_session_empty) {
                    $expire = 0;
                    $lifetime = $session_options['cookie_lifetime'] ?? null;
                    if ($lifetime) {
                        $expire = time() + $lifetime;
                    }
                    $response->headers->set_cookie(Cookie::create($session_name, $session_id, $expire, $session_cookie_path, $session_cookie_domain, $session_cookie_secure, $session_cookie_http_only, false, $session_cookie_same_site));
                }
            }
        }
        if ($session instanceof Session ? 0 === $session->get_usage_index() : !$session->is_started()) {
            return;
        }
        if ($auto_cache_control) {
            $max_age = $response->headers->has_cache_control_directive('public') ? 0 : (int) $response->get_max_age();
            $response->set_expires(new \DateTimeImmutable('+' . $max_age . ' seconds'))->set_private()->set_max_age($max_age)->headers->add_cache_control_directive('must-revalidate');
        }
        if (!$event->get_request()->attributes->get('_stateless', false)) {
            return;
        }
        if ($this->debug) {
            throw new Unexpected_Session_Usage_Exception('Session was used while the request was declared stateless.');
        }
        if ($this->container->has('logger')) {
            $this->container->get('logger')->warning('Session was used while the request was declared stateless.');
        }
    }
    /**
     * @internal
     */
    public function on_session_usage(): void
    {
        if (!$this->debug) {
            return;
        }
        if ($this->container?->has('session_collector')) {
            $this->container->get('session_collector')();
        }
        if (!$request_stack = $this->container?->has('request_stack') ? $this->container->get('request_stack') : null) {
            return;
        }
        $stateless = false;
        $cloned_request_stack = clone $request_stack;
        while (null !== ($request = $cloned_request_stack->pop()) && !$stateless) {
            $stateless = $request->attributes->get('_stateless');
        }
        if (!$stateless) {
            return;
        }
        if (!$session = $request_stack->get_current_request()->get_session()) {
            return;
        }
        if ($session->is_started()) {
            $session->save();
        }
        throw new Unexpected_Session_Usage_Exception('Session was used while the request was declared stateless.');
    }
    /**
     * @internal
     */
    public static function get_subscribed_events(): array
    {
        return [
            Kernel_Events::REQUEST => ['onKernelRequest', 128],
            // low priority to come after regular response listeners
            Kernel_Events::RESPONSE => ['onKernelResponse', -1000],
        ];
    }
    /**
     * @internal
     */
    public function reset(): void
    {
        if (\PHP_SESSION_ACTIVE === session_status()) {
            session_abort();
        }
        session_unset();
        $_SESSION = [];
        if (!headers_sent()) {
            // session id can only be reset when no headers were so we check for headers_sent first
            session_id('');
        }
    }
    /**
     * Gets the session object.
     *
     * @internal
     */
    abstract protected function get_session(): ?Session_Interface;
    private function get_session_options(array $session_options): array
    {
        $merged_session_options = [];
        foreach (session_get_cookie_params() as $key => $value) {
            $merged_session_options['cookie_' . $key] = $value;
        }
        foreach ($session_options as $key => $value) {
            // do the same logic as in the NativeSessionStorage
            if ('cookie_secure' === $key && 'auto' === $value) {
                continue;
            }
            $merged_session_options[$key] = $value;
        }
        return $merged_session_options;
    }
}