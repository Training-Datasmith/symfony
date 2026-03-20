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

use Symfony\Component\Console\Exception\Run_Command_Failed_Exception;
/**
 * @author Kevin Bond <kevinbond@gmail.com>
 */
class Run_Command_Message implements \Stringable
{
    /**
     * @param bool $throwOnFailure  If the command has a non-zero exit code, throw {@see RunCommandFailedException}
     * @param bool $catchExceptions @see Application::setCatchExceptions()
     */
    public function __construct(public readonly string $input, public readonly bool $throw_on_failure = true, public readonly bool $catch_exceptions = false)
    {
    }
    public function __toString(): string
    {
        return $this->input;
    }
}