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
 * Checks the HTTP port of a Request.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class Port_Request_Matcher implements Request_Matcher_Interface
{
    public function __construct(private readonly int $port)
    {
    }
    public function matches(Request $request): bool
    {
        return $request->get_port() === $this->port;
    }
}