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
namespace Symfony\Component\Http_Client\Retry;

use Symfony\Component\Http_Client\Response\Async_Context;
use Symfony\Contracts\Http_Client\Exception\Transport_Exception_Interface;
/**
 * @author Jérémy Derussé <jeremy@derusse.com>
 * @author Nicolas Grekas <p@tchwork.com>
 */
interface Retry_Strategy_Interface
{
    /**
     * Returns whether the request should be retried.
     *
     * @param ?string $responseContent Null is passed when the body did not arrive yet
     *
     * @return bool|null Returns null to signal that the body is required to take a decision
     */
    public function should_retry(Async_Context $context, ?string $response_content, ?Transport_Exception_Interface $exception): ?bool;
    /**
     * Returns the time to wait in milliseconds.
     */
    public function get_delay(Async_Context $context, ?string $response_content, ?Transport_Exception_Interface $exception): int;
}