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
namespace Symfony\Component\Http_Kernel\Exception;

/**
 * @author Ben Ramsey <ben@benramsey.com>
 */
class Service_Unavailable_Http_Exception extends Http_Exception
{
    /**
     * @param int|string|null $retryAfter The number of seconds or HTTP-date after which the request may be retried
     */
    public function __construct(int|string|null $retry_after = null, string $message = '', ?\Throwable $previous = null, int $code = 0, array $headers = [])
    {
        if ($retry_after) {
            $headers['Retry-After'] = $retry_after;
        }
        parent::__construct(503, $message, $previous, $headers, $code);
    }
}