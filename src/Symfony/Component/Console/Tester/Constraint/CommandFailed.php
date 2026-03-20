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
final class Command_Failed extends Constraint
{
    public function to_string(): string
    {
        return 'failed';
    }
    protected function matches($other): bool
    {
        return Command::FAILURE === $other;
    }
    protected function failure_description($other): string
    {
        return 'the command ' . $this->to_string();
    }
    protected function additional_failure_description($other): string
    {
        $mapping = [Command::SUCCESS => 'Command was successful.', Command::INVALID => 'Command was invalid.'];
        return $mapping[$other] ?? \sprintf('Command returned exit status %d.', $other);
    }
}