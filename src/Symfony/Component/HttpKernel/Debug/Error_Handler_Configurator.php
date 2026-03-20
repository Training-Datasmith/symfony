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
namespace Symfony\Component\Http_Kernel\Debug;

use Psr\Log\Logger_Interface;
use Symfony\Component\Error_Handler\Error_Handler;
/**
 * Configures the error handler.
 *
 * @final
 *
 * @internal
 */
class Error_Handler_Configurator
{
    private array|int|null $levels;
    private readonly ?int $throw_at;
    /**
     * @param array|int|null $levels  An array map of E_* to LogLevel::* or an integer bit field of E_* constants
     * @param int|null       $throwAt Thrown errors in a bit field of E_* constants, or null to keep the current value
     * @param bool           $scream  Enables/disables screaming mode, where even silenced errors are logged
     * @param bool           $scope   Enables/disables scoping mode
     */
    public function __construct(private ?Logger_Interface $logger = null, array|int|null $levels = \E_ALL, ?int $throw_at = \E_ALL, private readonly bool $scream = true, private readonly bool $scope = true, private ?Logger_Interface $deprecation_logger = null)
    {
        $this->levels = $levels ?? \E_ALL;
        $this->throw_at = \is_int($throw_at) ? $throw_at : (null === $throw_at ? null : ($throw_at ? \E_ALL : null));
    }
    /**
     * Configures the error handler.
     */
    public function configure(Error_Handler $handler): void
    {
        if ($this->logger || $this->deprecation_logger) {
            $this->set_default_loggers($handler);
            if (\is_array($this->levels)) {
                $levels = 0;
                foreach ($this->levels as $type => $log) {
                    $levels |= $type;
                }
            } else {
                $levels = $this->levels;
            }
            if ($this->scream) {
                $handler->scream_at($levels);
            }
            if ($this->scope) {
                $handler->scope_at($levels & ~\E_USER_DEPRECATED & ~\E_DEPRECATED);
            } else {
                $handler->scope_at(0, true);
            }
            $this->logger = $this->deprecation_logger = $this->levels = null;
        }
        if (null !== $this->throw_at) {
            $handler->throw_at($this->throw_at, true);
        }
    }
    private function set_default_loggers(Error_Handler $handler): void
    {
        if (\is_array($this->levels)) {
            $levels_deprecated_only = [];
            $levels_without_deprecated = [];
            foreach ($this->levels as $type => $log) {
                if (\E_DEPRECATED == $type || \E_USER_DEPRECATED == $type) {
                    $levels_deprecated_only[$type] = $log;
                } else {
                    $levels_without_deprecated[$type] = $log;
                }
            }
        } else {
            $levels_deprecated_only = $this->levels & (\E_DEPRECATED | \E_USER_DEPRECATED);
            $levels_without_deprecated = $this->levels & ~\E_DEPRECATED & ~\E_USER_DEPRECATED;
        }
        $default_logger_levels = $this->levels;
        if ($this->deprecation_logger && $levels_deprecated_only) {
            $handler->set_default_logger($this->deprecation_logger, $levels_deprecated_only);
            $default_logger_levels = $levels_without_deprecated;
        }
        if ($this->logger && $default_logger_levels) {
            $handler->set_default_logger($this->logger, $default_logger_levels);
        }
    }
}