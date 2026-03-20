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
namespace Symfony\Component\Console\Logger;

use Psr\Log\Abstract_Logger;
use Psr\Log\InvalidArgumentException;
use Psr\Log\Log_Level;
use Symfony\Component\Console\Output\Console_Output_Interface;
use Symfony\Component\Console\Output\Output_Interface;
/**
 * PSR-3 compliant console logger.
 *
 * @author Kévin Dunglas <dunglas@gmail.com>
 *
 * @see https://www.php-fig.org/psr/psr-3/
 */
class Console_Logger extends Abstract_Logger
{
    public const INFO = 'info';
    public const ERROR = 'error';
    private array $verbosity_level_map = [Log_Level::EMERGENCY => Output_Interface::VERBOSITY_NORMAL, Log_Level::ALERT => Output_Interface::VERBOSITY_NORMAL, Log_Level::CRITICAL => Output_Interface::VERBOSITY_NORMAL, Log_Level::ERROR => Output_Interface::VERBOSITY_NORMAL, Log_Level::WARNING => Output_Interface::VERBOSITY_NORMAL, Log_Level::NOTICE => Output_Interface::VERBOSITY_VERBOSE, Log_Level::INFO => Output_Interface::VERBOSITY_VERY_VERBOSE, Log_Level::DEBUG => Output_Interface::VERBOSITY_DEBUG];
    private array $format_level_map = [Log_Level::EMERGENCY => self::ERROR, Log_Level::ALERT => self::ERROR, Log_Level::CRITICAL => self::ERROR, Log_Level::ERROR => self::ERROR, Log_Level::WARNING => self::INFO, Log_Level::NOTICE => self::INFO, Log_Level::INFO => self::INFO, Log_Level::DEBUG => self::INFO];
    private bool $errored = false;
    public function __construct(private readonly Output_Interface $output, array $verbosity_level_map = [], array $format_level_map = [])
    {
        $this->verbosity_level_map = $verbosity_level_map + $this->verbosity_level_map;
        $this->format_level_map = $format_level_map + $this->format_level_map;
    }
    public function log($level, $message, array $context = []): void
    {
        if (!isset($this->verbosity_level_map[$level])) {
            throw new InvalidArgumentException(\sprintf('The log level "%s" does not exist.', $level));
        }
        $output = $this->output;
        // Write to the error output if necessary and available
        if (self::ERROR === $this->format_level_map[$level]) {
            if ($this->output instanceof Console_Output_Interface) {
                $output = $output->get_error_output();
            }
            $this->errored = true;
        }
        // the if condition check isn't necessary -- it's the same one that $output will do internally anyway.
        // We only do it for efficiency here as the message formatting is relatively expensive.
        if ($output->get_verbosity() >= $this->verbosity_level_map[$level]) {
            $output->writeln(\sprintf('<%1$s>[%2$s] %3$s</%1$s>', $this->format_level_map[$level], $level, $this->interpolate($message, $context)), $this->verbosity_level_map[$level]);
        }
    }
    /**
     * Returns true when any messages have been logged at error levels.
     */
    public function has_errored(): bool
    {
        return $this->errored;
    }
    /**
     * Interpolates context values into the message placeholders.
     *
     * @author PHP Framework Interoperability Group
     */
    private function interpolate(string $message, array $context): string
    {
        if (!str_contains($message, '{')) {
            return $message;
        }
        $replacements = [];
        foreach ($context as $key => $val) {
            if (null === $val || \is_scalar($val) || $val instanceof \Stringable) {
                $replacements["{{$key}}"] = $val;
            } elseif ($val instanceof \DateTimeInterface) {
                $replacements["{{$key}}"] = $val->format(\DateTimeInterface::RFC3339);
            } elseif (\is_object($val)) {
                $replacements["{{$key}}"] = '[object ' . $val::class . ']';
            } else {
                $replacements["{{$key}}"] = '[' . \gettype($val) . ']';
            }
        }
        return strtr($message, $replacements);
    }
}