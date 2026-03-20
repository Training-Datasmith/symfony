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
namespace Symfony\Component\Http_Kernel\Data_Collector;

use Symfony\Component\Http_Foundation\Request;
use Symfony\Component\Http_Foundation\Response;
/**
 * @author Bart van den Burg <bart@burgov.nl>
 *
 * @final
 */
class Ajax_Data_Collector extends Data_Collector
{
    public function collect(Request $request, Response $response, ?\Throwable $exception = null): void
    {
        // all collecting is done client side
    }
    public function reset(): void
    {
        // all collecting is done client side
    }
    public function get_name(): string
    {
        return 'ajax';
    }
}