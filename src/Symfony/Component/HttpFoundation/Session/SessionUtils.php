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
namespace Symfony\Component\Http_Foundation\Session;

/**
 * Session utility functions.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 * @author Rémon van de Kamp <rpkamp@gmail.com>
 *
 * @internal
 */
final class Session_Utils
{
    /**
     * Finds the session header amongst the headers that are to be sent, removes it, and returns
     * it so the caller can process it further.
     */
    public static function pop_session_cookie(
        string $session_name,
        #[\Sensitive_Parameter]
        string $session_id
    ): ?string
    {
        $session_cookie = null;
        $session_cookie_prefix = \sprintf(' %s=', urlencode($session_name));
        $session_cookie_with_id = \sprintf('%s%s;', $session_cookie_prefix, urlencode($session_id));
        $other_cookies = [];
        foreach (headers_list() as $h) {
            if (0 !== stripos($h, 'Set-Cookie:')) {
                continue;
            }
            if (11 === strpos($h, $session_cookie_prefix, 11)) {
                $session_cookie = $h;
                if (11 !== strpos($h, $session_cookie_with_id, 11)) {
                    $other_cookies[] = $h;
                }
            } else {
                $other_cookies[] = $h;
            }
        }
        if (null === $session_cookie) {
            return null;
        }
        header_remove('Set-Cookie');
        foreach ($other_cookies as $h) {
            header($h, false);
        }
        return $session_cookie;
    }
}