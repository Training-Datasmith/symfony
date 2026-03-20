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
use Doctrine\DBAL\Driver\Connection as ConnectionInterface;
use Doctrine\DBAL\Driver\Middleware\Abstract_Driver_Middleware;
use Symfony\Component\Stopwatch\Stopwatch;
/**
 * @author Laurent VOULLEMIER <laurent.voullemier@gmail.com>
 *
 * @internal
 */
final class Driver extends Abstract_Driver_Middleware
{
    public function __construct(Driver_Interface $driver, private readonly Debug_Data_Holder $debug_data_holder, private readonly ?Stopwatch $stopwatch, private readonly string $connection_name)
    {
        parent::__construct($driver);
    }
    public function connect(array $params): Connection_Interface
    {
        $connection = parent::connect($params);
        return new Connection($connection, $this->debug_data_holder, $this->stopwatch, $this->connection_name);
    }
}