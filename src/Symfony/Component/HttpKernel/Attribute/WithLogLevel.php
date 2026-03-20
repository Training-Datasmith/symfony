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
namespace Symfony\Component\Http_Kernel\Attribute;

use Psr\Log\Log_Level;
/**
 * Defines the log level applied to an exception.
 *
 * @author Dejan Angelov <angelovdejan@protonmail.com>
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final readonly class With_Log_Level
{
    /**
     * @param LogLevel::* $level The level to use to log the exception
     */
    public function __construct(public string $level)
    {
        if (!\defined('Psr\Log\LogLevel::' . strtoupper($this->level))) {
            throw new \InvalidArgumentException(\sprintf('Invalid log level "%s".', $this->level));
        }
    }
}