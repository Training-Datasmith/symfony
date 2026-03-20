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
namespace Symfony\Component\Console\Helper;

/**
 * Helps outputting debug information when running an external program from a command.
 *
 * An external program can be a Process, an HTTP request, or anything else.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class Debug_Formatter_Helper extends Helper
{
    private const COLORS = ['black', 'red', 'green', 'yellow', 'blue', 'magenta', 'cyan', 'white', 'default'];
    private array $started = [];
    private int $count = -1;
    /**
     * Starts a debug formatting session.
     */
    public function start(string $id, string $message, string $prefix = 'RUN'): string
    {
        $this->started[$id] = ['border' => ++$this->count % \count(self::COLORS)];
        return \sprintf("%s<bg=blue;fg=white> %s </> <fg=blue>%s</>\n", $this->get_border($id), $prefix, $message);
    }
    /**
     * Adds progress to a formatting session.
     */
    public function progress(string $id, string $buffer, bool $error = false, string $prefix = 'OUT', string $error_prefix = 'ERR'): string
    {
        $message = '';
        if ($error) {
            if (isset($this->started[$id]['out'])) {
                $message .= "\n";
                unset($this->started[$id]['out']);
            }
            if (!isset($this->started[$id]['err'])) {
                $message .= \sprintf('%s<bg=red;fg=white> %s </> ', $this->get_border($id), $error_prefix);
                $this->started[$id]['err'] = true;
            }
            $message .= str_replace("\n", \sprintf("\n%s<bg=red;fg=white> %s </> ", $this->get_border($id), $error_prefix), $buffer);
        } else {
            if (isset($this->started[$id]['err'])) {
                $message .= "\n";
                unset($this->started[$id]['err']);
            }
            if (!isset($this->started[$id]['out'])) {
                $message .= \sprintf('%s<bg=green;fg=white> %s </> ', $this->get_border($id), $prefix);
                $this->started[$id]['out'] = true;
            }
            $message .= str_replace("\n", \sprintf("\n%s<bg=green;fg=white> %s </> ", $this->get_border($id), $prefix), $buffer);
        }
        return $message;
    }
    /**
     * Stops a formatting session.
     */
    public function stop(string $id, string $message, bool $successful, string $prefix = 'RES'): string
    {
        $trailing_eol = isset($this->started[$id]['out']) || isset($this->started[$id]['err']) ? "\n" : '';
        if ($successful) {
            return \sprintf("%s%s<bg=green;fg=white> %s </> <fg=green>%s</>\n", $trailing_eol, $this->get_border($id), $prefix, $message);
        }
        $message = \sprintf("%s%s<bg=red;fg=white> %s </> <fg=red>%s</>\n", $trailing_eol, $this->get_border($id), $prefix, $message);
        unset($this->started[$id]['out'], $this->started[$id]['err']);
        return $message;
    }
    private function get_border(string $id): string
    {
        return \sprintf('<bg=%s> </>', self::COLORS[$this->started[$id]['border']]);
    }
    public function get_name(): string
    {
        return 'debug_formatter';
    }
}