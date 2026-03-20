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
namespace Symfony\Component\Console\Messenger;

/**
 * @author Kevin Bond <kevinbond@gmail.com>
 */
final readonly class Run_Command_Context
{
    public function __construct(public Run_Command_Message $message, public int $exit_code, public string $output)
    {
    }
}