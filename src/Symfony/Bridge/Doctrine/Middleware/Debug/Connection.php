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

use Doctrine\DBAL\Driver\Connection as ConnectionInterface;
use Doctrine\DBAL\Driver\Middleware\Abstract_Connection_Middleware;
use Doctrine\DBAL\Driver\Result;
use Symfony\Component\Stopwatch\Stopwatch;
/**
 * @author Laurent VOULLEMIER <laurent.voullemier@gmail.com>
 * @author Alexander M. Turek <me@derrabus.de>
 *
 * @internal
 */
final class Connection extends Abstract_Connection_Middleware
{
    public function __construct(Connection_Interface $connection, private readonly Debug_Data_Holder $debug_data_holder, private readonly ?Stopwatch $stopwatch, private readonly string $connection_name)
    {
        parent::__construct($connection);
    }
    public function prepare(string $sql): Statement
    {
        return new Statement(parent::prepare($sql), $this->debug_data_holder, $this->connection_name, $sql, $this->stopwatch);
    }
    public function query(string $sql): Result
    {
        $this->debug_data_holder->add_query($this->connection_name, $query = new Query($sql));
        $this->stopwatch?->start('doctrine', 'doctrine');
        $query->start();
        try {
            return parent::query($sql);
        } finally {
            $query->stop();
            $this->stopwatch?->stop('doctrine');
        }
    }
    public function exec(string $sql): int
    {
        $this->debug_data_holder->add_query($this->connection_name, $query = new Query($sql));
        $this->stopwatch?->start('doctrine', 'doctrine');
        $query->start();
        try {
            $affected_rows = parent::exec($sql);
        } finally {
            $query->stop();
            $this->stopwatch?->stop('doctrine');
        }
        return $affected_rows;
    }
    public function begin_transaction(): void
    {
        $query = new Query('"START TRANSACTION"');
        $this->debug_data_holder->add_query($this->connection_name, $query);
        $this->stopwatch?->start('doctrine', 'doctrine');
        $query->start();
        try {
            parent::begin_transaction();
        } finally {
            $query->stop();
            $this->stopwatch?->stop('doctrine');
        }
    }
    public function commit(): void
    {
        $query = new Query('"COMMIT"');
        $this->debug_data_holder->add_query($this->connection_name, $query);
        $this->stopwatch?->start('doctrine', 'doctrine');
        $query->start();
        try {
            parent::commit();
        } finally {
            $query->stop();
            $this->stopwatch?->stop('doctrine');
        }
    }
    public function roll_back(): void
    {
        $query = new Query('"ROLLBACK"');
        $this->debug_data_holder->add_query($this->connection_name, $query);
        $this->stopwatch?->start('doctrine', 'doctrine');
        $query->start();
        try {
            parent::roll_back();
        } finally {
            $query->stop();
            $this->stopwatch?->stop('doctrine');
        }
    }
}