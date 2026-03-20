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
namespace Symfony\Component\Http_Foundation\Session\Storage\Handler;

use Symfony\Component\Http_Foundation\Session\Session_Utils;
/**
 * This abstract session handler provides a generic implementation
 * of the PHP 7.0 SessionUpdateTimestampHandlerInterface,
 * enabling strict and lazy session handling.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
abstract class Abstract_Session_Handler implements \Session_Handler_Interface, \Session_Update_Timestamp_Handler_Interface
{
    private string $session_name;
    private string $prefetch_id;
    private string $prefetch_data;
    private ?string $new_session_id = null;
    private string $igbinary_empty_data;
    public function open(string $save_path, string $session_name): bool
    {
        $this->session_name = $session_name;
        if (!headers_sent() && !\ini_get('session.cache_limiter') && '0' !== \ini_get('session.cache_limiter')) {
            header(\sprintf('Cache-Control: max-age=%d, private, must-revalidate', 60 * (int) \ini_get('session.cache_expire')));
        }
        return true;
    }
    abstract protected function do_read(
        #[\Sensitive_Parameter]
        string $session_id
    ): string;
    abstract protected function do_write(
        #[\Sensitive_Parameter]
        string $session_id,
        string $data
    ): bool;
    abstract protected function do_destroy(
        #[\Sensitive_Parameter]
        string $session_id
    ): bool;
    public function validate_id(
        #[\Sensitive_Parameter]
        string $session_id
    ): bool
    {
        $this->prefetch_data = $this->read($session_id);
        $this->prefetch_id = $session_id;
        return '' !== $this->prefetch_data;
    }
    public function read(
        #[\Sensitive_Parameter]
        string $session_id
    ): string
    {
        if (isset($this->prefetch_id)) {
            $prefetch_id = $this->prefetch_id;
            $prefetch_data = $this->prefetch_data;
            unset($this->prefetch_id, $this->prefetch_data);
            if ($prefetch_id === $session_id || '' === $prefetch_data) {
                $this->new_session_id = '' === $prefetch_data ? $session_id : null;
                return $prefetch_data;
            }
        }
        $data = $this->do_read($session_id);
        $this->new_session_id = '' === $data ? $session_id : null;
        return $data;
    }
    public function update_timestamp(
        #[\Sensitive_Parameter]
        string $session_id,
        string $data
    ): bool
    {
        $this->igbinary_empty_data ??= \function_exists('igbinary_serialize') ? igbinary_serialize([]) : '';
        if ('' === $data || $this->igbinary_empty_data === $data) {
            return $this->destroy($session_id);
        }
        return true;
    }
    public function write(
        #[\Sensitive_Parameter]
        string $session_id,
        string $data
    ): bool
    {
        // see https://github.com/igbinary/igbinary/issues/146
        $this->igbinary_empty_data ??= \function_exists('igbinary_serialize') ? igbinary_serialize([]) : '';
        if ('' === $data || $this->igbinary_empty_data === $data) {
            return $this->destroy($session_id);
        }
        $this->new_session_id = null;
        return $this->do_write($session_id, $data);
    }
    public function destroy(
        #[\Sensitive_Parameter]
        string $session_id
    ): bool
    {
        if (!headers_sent() && filter_var(\ini_get('session.use_cookies'), \FILTER_VALIDATE_BOOL)) {
            if (!isset($this->session_name)) {
                throw new \LogicException(\sprintf('Session name cannot be empty, did you forget to call "parent::open()" in "%s"?.', static::class));
            }
            $cookie = Session_Utils::pop_session_cookie($this->session_name, $session_id);
            /*
             * We send an invalidation Set-Cookie header (zero lifetime)
             * when either the session was started or a cookie with
             * the session name was sent by the client (in which case
             * we know it's invalid as a valid session cookie would've
             * started the session).
             */
            if (null === $cookie || isset($_COOKIE[$this->session_name])) {
                $params = session_get_cookie_params();
                unset($params['lifetime']);
                setcookie($this->session_name, '', $params);
            }
        }
        return $this->new_session_id === $session_id || $this->do_destroy($session_id);
    }
}