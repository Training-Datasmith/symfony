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

use Symfony\Component\Expression_Language\Expression;
use Symfony\Component\Expression_Language\Expression_Language;
use Symfony\Component\Http_Foundation\Request;
use Symfony\Component\Http_Foundation\Request_Matcher_Interface;
/**
 * ExpressionRequestMatcher uses an expression to match a Request.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class Expression_Request_Matcher implements Request_Matcher_Interface
{
    public function __construct(private readonly Expression_Language $language, private readonly Expression|string $expression)
    {
    }
    public function matches(Request $request): bool
    {
        return $this->language->evaluate($this->expression, ['request' => $request, 'method' => $request->get_method(), 'path' => rawurldecode($request->get_path_info()), 'host' => $request->get_host(), 'ip' => $request->get_client_ip(), 'attributes' => $request->attributes->all()]);
    }
}