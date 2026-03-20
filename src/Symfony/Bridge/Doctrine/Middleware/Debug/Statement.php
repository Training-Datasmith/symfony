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

use Doctrine\DBAL\Driver\Middleware\Abstract_Statement_Middleware;
use Doctrine\DBAL\Driver\Result as ResultInterface;
use Doctrine\DBAL\Driver\Statement as StatementInterface;
use Doctrine\DBAL\Parameter_Type;
use Symfony\Component\Stopwatch\Stopwatch;
/**
 * @author Laurent VOULLEMIER <laurent.voullemier@gmail.com>
 * @author Alexander M. Turek <me@derrabus.de>
 *
 * @internal
 */
final class Statement extends Abstract_Statement_Middleware
{
    private readonly Query $query;
    public function __construct(Statement_Interface $statement, private readonly Debug_Data_Holder $debug_data_holder, private readonly string $connection_name, string $sql, private readonly ?Stopwatch $stopwatch = null)
    {
        parent::__construct($statement);
        $this->query = new Query($sql);
    }
    public function bind_value(int|string $param, mixed $value, Parameter_Type $type): void
    {
        $this->query->set_value($param, $value, $type);
        parent::bind_value($param, $value, $type);
    }
    public function execute(): Result_Interface
    {
        // clone to prevent variables by reference to change
        $this->debug_data_holder->add_query($this->connection_name, $query = clone $this->query);
        $this->stopwatch?->start('doctrine', 'doctrine');
        $query->start();
        try {
            return parent::execute();
        } finally {
            $query->stop();
            $this->stopwatch?->stop('doctrine');
        }
    }
}