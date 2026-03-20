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
use Symfony\Component\Rate_Limiter\Rate_Limit;
/**
 * A special type of limiter that deals with requests.
 *
 * This allows to limit on different types of information
 * from the requests.
 *
 * @author Wouter de Jong <wouter@wouterj.nl>
 */
interface Request_Rate_Limiter_Interface
{
    public function consume(Request $request): Rate_Limit;
    public function reset(Request $request): void;
}