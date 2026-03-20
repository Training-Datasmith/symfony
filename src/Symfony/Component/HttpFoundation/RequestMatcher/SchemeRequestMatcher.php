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
 * Checks the HTTP scheme of a Request.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class Scheme_Request_Matcher implements Request_Matcher_Interface
{
    /**
     * @var string[]
     */
    private readonly array $schemes;
    /**
     * @param string[]|string $schemes A scheme or a list of schemes
     *                                 Strings can contain a comma-delimited list of schemes
     */
    public function __construct(array|string $schemes)
    {
        $this->schemes = array_reduce(array_map(strtolower(...), (array) $schemes), static fn(array $schemes, string $scheme): array => array_merge($schemes, preg_split('/\s*,\s*/', $scheme)), []);
    }
    public function matches(Request $request): bool
    {
        if (!$this->schemes) {
            return true;
        }
        return \in_array($request->get_scheme(), $this->schemes, true);
    }
}