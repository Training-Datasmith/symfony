<?php

declare(strict_types=1);

use Symfony\Component\Console\Command\Command;

class Foo4Command extends Command
{
    protected function configure(): void
    {
        $this->setName('foo3:bar:toh');
    }
}
