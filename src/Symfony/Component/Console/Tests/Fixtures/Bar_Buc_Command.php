<?php

declare(strict_types=1);

use Symfony\Component\Console\Command\Command;

class BarBucCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('bar:buc');
    }
}
