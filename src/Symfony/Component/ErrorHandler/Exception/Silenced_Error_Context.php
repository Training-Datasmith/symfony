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
namespace Symfony\Component\Error_Handler\Exception;

/**
 * Data Object that represents a Silenced Error.
 *
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 */
class Silenced_Error_Context implements \JsonSerializable
{
    public function __construct(private readonly int $severity, private readonly string $file, private readonly int $line, private readonly array $trace = [], public int $count = 1)
    {
    }
    public function get_severity(): int
    {
        return $this->severity;
    }
    public function get_file(): string
    {
        return $this->file;
    }
    public function get_line(): int
    {
        return $this->line;
    }
    public function get_trace(): array
    {
        return $this->trace;
    }
    public function jsonSerialize(): array
    {
        return ['severity' => $this->severity, 'file' => $this->file, 'line' => $this->line, 'trace' => $this->trace, 'count' => $this->count];
    }
}