<?php

declare(strict_types=1);

use Symfony\Component\Console\Command\Command;

class Foo6Command extends Command
{
    protected function configure(): void
    {
        $this->setName('0foo:bar')->setDescription('0foo:bar command');
    }
}
