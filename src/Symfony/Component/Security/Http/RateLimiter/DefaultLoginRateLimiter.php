<?php

declare(strict_types=1);

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\RateLimiter;

use Symfony\Component\HttpFoundation\RateLimiter\AbstractRequestRateLimiter;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Security\Core\Exception\InvalidArgumentException;
use Symfony\Component\Security\Http\SecurityRequestAttributes;

/**
 * A default login throttling limiter.
 *
 * This limiter prevents breadth-first attacks by enforcing
 * a limit on username+IP and a (higher) limit on IP.
 *
 * @author Wouter de Jong <wouter@wouterj.nl>
 */
final class DefaultLoginRateLimiter extends AbstractRequestRateLimiter
{
    /**
     * @param non-empty-string $secret A secret to use for hashing the IP address and username
     */
    public function __construct(
        private readonly RateLimiterFactory $globalFactory,
        private readonly RateLimiterFactory $localFactory,
        #[\SensitiveParameter] private readonly string $secret,
    ) {
        if (!$secret) {
            throw new InvalidArgumentException('A non-empty secret is required.');
        }
    }

    protected function getLimiters(Request $request): array
    {
        $username = $request->attributes->get(SecurityRequestAttributes::LAST_USERNAME, '');
        $username = preg_match('//u', (string) $username) ? mb_strtolower((string) $username, 'UTF-8') : strtolower((string) $username);

        return [
            $this->globalFactory->create($this->hash($request->getClientIp())),
            $this->localFactory->create($this->hash($username.'-'.$request->getClientIp())),
        ];
    }

    private function hash(string $data): string
    {
        return strtr(substr(base64_encode(hash_hmac('sha256', $data, $this->secret, true)), 0, 8), '/+', '._');
    }
}
