<?php

declare(strict_types=1);

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Monolog\Formatter;

use Monolog\Formatter\FormatterInterface;
use Monolog\LogRecord;
use Symfony\Component\VarDumper\Cloner\VarCloner;

/**
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 */
final readonly class VarDumperFormatter implements FormatterInterface
{
    public function __construct(private ?VarCloner $cloner = new VarCloner())
    {
    }

    public function format(LogRecord $record): mixed
    {
        $record = $record->toArray();

        $record['context'] = $this->cloner->cloneVar($record['context']);
        $record['extra'] = $this->cloner->cloneVar($record['extra']);

        return $record;
    }

    public function formatBatch(array $records): mixed
    {
        foreach ($records as $k => $record) {
            $record[$k] = $this->format($record);
        }

        return $records;
    }
}
