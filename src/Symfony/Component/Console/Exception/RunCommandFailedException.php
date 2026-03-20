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
namespace Symfony\Component\Console\Exception;

use Symfony\Component\Console\Messenger\Run_Command_Context;
/**
 * @author Kevin Bond <kevinbond@gmail.com>
 */
final class Run_Command_Failed_Exception extends RuntimeException
{
    public function __construct(\Throwable|string $exception, public readonly Run_Command_Context $context)
    {
        parent::__construct($exception instanceof \Throwable ? $exception->get_message() : $exception, $exception instanceof \Throwable && \is_int($exception->get_code()) ? $exception->get_code() : 0, $exception instanceof \Throwable ? $exception : null);
    }
}