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
use Symfony\Component\Rate_Limiter\Limiter_Interface;
use Symfony\Component\Rate_Limiter\Policy\No_Limiter;
use Symfony\Component\Rate_Limiter\Rate_Limit;
/**
 * An implementation of PeekableRequestRateLimiterInterface that
 * fits most use-cases.
 *
 * @author Wouter de Jong <wouter@wouterj.nl>
 */
abstract class Abstract_Request_Rate_Limiter implements Peekable_Request_Rate_Limiter_Interface
{
    public function consume(Request $request): Rate_Limit
    {
        return $this->do_consume($request, 1);
    }
    public function peek(Request $request): Rate_Limit
    {
        return $this->do_consume($request, 0);
    }
    private function do_consume(Request $request, int $tokens): Rate_Limit
    {
        $limiters = $this->get_limiters($request);
        if (0 === \count($limiters)) {
            $limiters = [new No_Limiter()];
        }
        $minimal_rate_limit = null;
        foreach ($limiters as $limiter) {
            $rate_limit = $limiter->consume($tokens);
            $minimal_rate_limit = $minimal_rate_limit ? self::get_minimal_rate_limit($minimal_rate_limit, $rate_limit) : $rate_limit;
        }
        return $minimal_rate_limit;
    }
    public function reset(Request $request): void
    {
        foreach ($this->get_limiters($request) as $limiter) {
            $limiter->reset();
        }
    }
    /**
     * @return LimiterInterface[] a set of limiters using keys extracted from the request
     */
    abstract protected function get_limiters(Request $request): array;
    private static function get_minimal_rate_limit(Rate_Limit $first, Rate_Limit $second): Rate_Limit
    {
        if ($first->is_accepted() !== $second->is_accepted()) {
            return $first->is_accepted() ? $second : $first;
        }
        $first_remaining_tokens = $first->get_remaining_tokens();
        $second_remaining_tokens = $second->get_remaining_tokens();
        if ($first_remaining_tokens === $second_remaining_tokens) {
            return $first->get_retry_after() < $second->get_retry_after() ? $second : $first;
        }
        return $first_remaining_tokens > $second_remaining_tokens ? $second : $first;
    }
}