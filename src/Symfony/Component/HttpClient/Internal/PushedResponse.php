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
namespace Symfony\Component\Http_Client\Internal;

use Symfony\Component\Http_Client\Response\Curl_Response;
/**
 * A pushed response with its request headers.
 *
 * @author Alexander M. Turek <me@derrabus.de>
 *
 * @internal
 */
final class Pushed_Response
{
    public function __construct(public Curl_Response $response, public array $request_headers, public array $parent_options, public \Curl_Handle $handle)
    {
    }
}