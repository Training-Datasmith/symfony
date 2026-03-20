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
 * Checks the Request attributes matches all regular expressions.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class Attributes_Request_Matcher implements Request_Matcher_Interface
{
    /**
     * @param array<string, string> $regexps
     */
    public function __construct(private readonly array $regexps)
    {
    }
    public function matches(Request $request): bool
    {
        foreach ($this->regexps as $key => $regexp) {
            $attribute = $request->attributes->get($key);
            if (!\is_string($attribute)) {
                return false;
            }
            if (!preg_match('{' . $regexp . '}', $attribute)) {
                return false;
            }
        }
        return true;
    }
}