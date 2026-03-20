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
use Symfony\Contracts\Service\Reset_Interface;
/**
 * DataCollectorInterface.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
interface Data_Collector_Interface extends Reset_Interface
{
    /**
     * Collects data for the given Request and Response.
     */
    public function collect(Request $request, Response $response, ?\Throwable $exception = null): void;
    /**
     * Returns the name of the collector.
     */
    public function get_name(): string;
}