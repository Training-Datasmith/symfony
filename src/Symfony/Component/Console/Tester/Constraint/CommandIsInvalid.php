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
namespace Symfony\Component\Console\Tester\Constraint;

use Php_Unit\Framework\Constraint\Constraint;
use Symfony\Component\Console\Command\Command;
final class Command_Is_Invalid extends Constraint
{
    public function to_string(): string
    {
        return 'is invalid';
    }
    protected function matches($other): bool
    {
        return Command::INVALID === $other;
    }
    protected function failure_description($other): string
    {
        return 'the command ' . $this->to_string();
    }
    protected function additional_failure_description($other): string
    {
        $mapping = [Command::SUCCESS => 'Command was successful.', Command::FAILURE => 'Command failed.'];
        return $mapping[$other] ?? \sprintf('Command returned exit status %d.', $other);
    }
}