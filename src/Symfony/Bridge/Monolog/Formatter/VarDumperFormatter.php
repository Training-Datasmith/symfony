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
namespace Symfony\Bridge\Monolog\Formatter;

use Monolog\Formatter\Formatter_Interface;
use Monolog\Log_Record;
use Symfony\Component\Var_Dumper\Cloner\Var_Cloner;
/**
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 */
final readonly class Var_Dumper_Formatter implements Formatter_Interface
{
    public function __construct(private ?Var_Cloner $cloner = new Var_Cloner())
    {
    }
    public function format(Log_Record $record): mixed
    {
        $record = $record->to_array();
        $record['context'] = $this->cloner->clone_var($record['context']);
        $record['extra'] = $this->cloner->clone_var($record['extra']);
        return $record;
    }
    public function format_batch(array $records): mixed
    {
        foreach ($records as $k => $record) {
            $record[$k] = $this->format($record);
        }
        return $records;
    }
}