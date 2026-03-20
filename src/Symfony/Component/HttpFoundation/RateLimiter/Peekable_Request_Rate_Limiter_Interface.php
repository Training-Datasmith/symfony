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
namespace Symfony\Component\Http_Foundation\Rate_Limiter;

use Symfony\Component\Http_Foundation\Request;
use Symfony\Component\Rate_Limiter\Rate_Limit;
/**
 * A request limiter which allows peeking ahead.
 *
 * This is valuable to reduce the cache backend load in scenarios
 * like a login when we only want to consume a token on login failure,
 * and where the majority of requests will be successful and thus not
 * need to consume a token.
 *
 * This way we can peek ahead before allowing the request through, and
 * only consume if the request failed (1 backend op). This is compared
 * to always consuming and then resetting the limit if the request
 * is successful (2 backend ops).
 *
 * @author Jordi Boggiano <j.boggiano@seld.be>
 */
interface Peekable_Request_Rate_Limiter_Interface extends Request_Rate_Limiter_Interface
{
    public function peek(Request $request): Rate_Limit;
}