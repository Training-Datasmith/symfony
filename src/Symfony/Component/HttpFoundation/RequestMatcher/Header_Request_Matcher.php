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
namespace Symfony\Component\Http_Foundation\Request_Matcher;

use Symfony\Component\Http_Foundation\Request;
use Symfony\Component\Http_Foundation\Request_Matcher_Interface;
/**
 * Checks the presence of HTTP headers in a Request.
 *
 * @author Alexandre Daubois <alex.daubois@gmail.com>
 */
class Header_Request_Matcher implements Request_Matcher_Interface
{
    /**
     * @var string[]
     */
    private readonly array $headers;
    /**
     * @param string[]|string $headers A header or a list of headers
     *                                 Strings can contain a comma-delimited list of headers
     */
    public function __construct(array|string $headers)
    {
        $this->headers = array_reduce((array) $headers, static fn(array $headers, string $header): array => array_merge($headers, preg_split('/\s*,\s*/', $header)), []);
    }
    public function matches(Request $request): bool
    {
        if (!$this->headers) {
            return true;
        }
        foreach ($this->headers as $header) {
            if (!$request->headers->has($header)) {
                return false;
            }
        }
        return true;
    }
}