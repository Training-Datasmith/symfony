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
 * Checks the HTTP method of a Request.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class Method_Request_Matcher implements Request_Matcher_Interface
{
    /**
     * @var string[]
     */
    private array $methods = [];
    /**
     * @param string[]|string $methods An HTTP method or an array of HTTP methods
     *                                 Strings can contain a comma-delimited list of methods
     */
    public function __construct(array|string $methods)
    {
        $this->methods = array_reduce(array_map(strtoupper(...), (array) $methods), static fn(array $methods, string $method): array => array_merge($methods, preg_split('/\s*,\s*/', $method)), []);
    }
    public function matches(Request $request): bool
    {
        if (!$this->methods) {
            return true;
        }
        return \in_array($request->get_method(), $this->methods, true);
    }
}