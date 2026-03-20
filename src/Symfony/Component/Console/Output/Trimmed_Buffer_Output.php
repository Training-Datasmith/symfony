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
namespace Symfony\Component\Console\Output;

use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Formatter\Output_Formatter_Interface;
/**
 * A BufferedOutput that keeps only the last N chars.
 *
 * @author Jérémy Derussé <jeremy@derusse.com>
 */
class Trimmed_Buffer_Output extends Output
{
    private readonly int $max_length;
    private string $buffer = '';
    public function __construct(int $max_length, ?int $verbosity = self::VERBOSITY_NORMAL, bool $decorated = false, ?Output_Formatter_Interface $formatter = null)
    {
        if ($max_length <= 0) {
            throw new InvalidArgumentException(\sprintf('"%s()" expects a strictly positive maxLength. Got %d.', __METHOD__, $max_length));
        }
        parent::__construct($verbosity, $decorated, $formatter);
        $this->max_length = $max_length;
    }
    /**
     * Empties buffer and returns its content.
     */
    public function fetch(): string
    {
        $content = $this->buffer;
        $this->buffer = '';
        return $content;
    }
    protected function do_write(string $message, bool $newline): void
    {
        $this->buffer .= $message;
        if ($newline) {
            $this->buffer .= \PHP_EOL;
        }
        $this->buffer = substr($this->buffer, -$this->max_length);
    }
}