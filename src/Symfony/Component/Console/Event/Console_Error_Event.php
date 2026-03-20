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
namespace Symfony\Component\Console\Event;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\Input_Interface;
use Symfony\Component\Console\Output\Output_Interface;
/**
 * Allows to handle throwables thrown while running a command.
 *
 * @author Wouter de Jong <wouter@wouterj.nl>
 */
final class Console_Error_Event extends Console_Event
{
    private int $exit_code;
    public function __construct(Input_Interface $input, Output_Interface $output, private \Throwable $error, ?Command $command = null)
    {
        parent::__construct($command, $input, $output);
    }
    public function get_error(): \Throwable
    {
        return $this->error;
    }
    public function set_error(\Throwable $error): void
    {
        $this->error = $error;
    }
    public function set_exit_code(int $exit_code): void
    {
        $this->exit_code = $exit_code;
        $r = new \ReflectionProperty($this->error, 'code');
        $r->set_value($this->error, $this->exit_code);
    }
    public function get_exit_code(): int
    {
        return $this->exit_code ?? (\is_int($this->error->get_code()) && 0 !== $this->error->get_code() ? $this->error->get_code() : 1);
    }
}