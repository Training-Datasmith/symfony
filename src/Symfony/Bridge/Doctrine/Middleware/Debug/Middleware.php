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
namespace Symfony\Bridge\Doctrine\Middleware\Debug;

use Doctrine\DBAL\Driver as DriverInterface;
use Doctrine\DBAL\Driver\Middleware as MiddlewareInterface;
use Symfony\Component\Stopwatch\Stopwatch;
/**
 * Middleware to collect debug data.
 *
 * @author Laurent VOULLEMIER <laurent.voullemier@gmail.com>
 */
final readonly class Middleware implements Middleware_Interface
{
    public function __construct(private Debug_Data_Holder $debug_data_holder, private ?Stopwatch $stopwatch, private string $connection_name = 'default')
    {
    }
    public function wrap(Driver_Interface $driver): Driver_Interface
    {
        return new Driver($driver, $this->debug_data_holder, $this->stopwatch, $this->connection_name);
    }
}