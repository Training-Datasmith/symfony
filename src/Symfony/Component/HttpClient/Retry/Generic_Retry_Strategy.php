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

use Symfony\Component\Http_Client\Exception\InvalidArgumentException;
use Symfony\Component\Http_Client\Response\Async_Context;
use Symfony\Contracts\Http_Client\Exception\Transport_Exception_Interface;
/**
 * Decides to retry the request when HTTP status codes belong to the given list of codes.
 *
 * @author Jérémy Derussé <jeremy@derusse.com>
 */
class Generic_Retry_Strategy implements Retry_Strategy_Interface
{
    public const IDEMPOTENT_METHODS = ['GET', 'HEAD', 'PUT', 'DELETE', 'OPTIONS', 'TRACE', 'QUERY'];
    public const DEFAULT_RETRY_STATUS_CODES = [
        0 => self::IDEMPOTENT_METHODS,
        // for transport exceptions
        423,
        425,
        429,
        500 => self::IDEMPOTENT_METHODS,
        502,
        503,
        504 => self::IDEMPOTENT_METHODS,
        507 => self::IDEMPOTENT_METHODS,
        510 => self::IDEMPOTENT_METHODS,
    ];
    /**
     * @param array $statusCodes List of HTTP status codes that trigger a retry
     * @param int   $delayMs     Amount of time to delay (or the initial value when multiplier is used)
     * @param float $multiplier  Multiplier to apply to the delay each time a retry occurs
     * @param int   $maxDelayMs  Maximum delay to allow (0 means no maximum)
     * @param float $jitter      Probability of randomness int delay (0 = none, 1 = 100% random)
     */
    public function __construct(private array $status_codes = self::DEFAULT_RETRY_STATUS_CODES, private readonly int $delay_ms = 1000, private readonly float $multiplier = 2.0, private readonly int $max_delay_ms = 0, private readonly float $jitter = 0.1)
    {
        if ($delay_ms < 0) {
            throw new InvalidArgumentException(\sprintf('Delay must be greater than or equal to zero: "%s" given.', $delay_ms));
        }
        if ($multiplier < 1) {
            throw new InvalidArgumentException(\sprintf('Multiplier must be greater than or equal to one: "%s" given.', $multiplier));
        }
        if ($max_delay_ms < 0) {
            throw new InvalidArgumentException(\sprintf('Max delay must be greater than or equal to zero: "%s" given.', $max_delay_ms));
        }
        if ($jitter < 0 || $jitter > 1) {
            throw new InvalidArgumentException(\sprintf('Jitter must be between 0 and 1: "%s" given.', $jitter));
        }
    }
    public function should_retry(Async_Context $context, ?string $response_content, ?Transport_Exception_Interface $exception): ?bool
    {
        $status_code = $context->get_status_code();
        if (\in_array($status_code, $this->status_codes, true)) {
            return true;
        }
        if (isset($this->status_codes[$status_code]) && \is_array($this->status_codes[$status_code])) {
            return \in_array($context->get_info('http_method'), $this->status_codes[$status_code], true);
        }
        if (null === $exception) {
            return false;
        }
        if (\in_array(0, $this->status_codes, true)) {
            return true;
        }
        if (isset($this->status_codes[0]) && \is_array($this->status_codes[0])) {
            return \in_array($context->get_info('http_method'), $this->status_codes[0], true);
        }
        return false;
    }
    public function get_delay(Async_Context $context, ?string $response_content, ?Transport_Exception_Interface $exception): int
    {
        $delay = $this->delay_ms * $this->multiplier ** $context->get_info('retry_count');
        if ($this->jitter > 0) {
            $randomness = (int) ($delay * $this->jitter);
            $delay += random_int(-$randomness, +$randomness);
        }
        if ($delay > $this->max_delay_ms && 0 !== $this->max_delay_ms) {
            return $this->max_delay_ms;
        }
        return (int) $delay;
    }
}