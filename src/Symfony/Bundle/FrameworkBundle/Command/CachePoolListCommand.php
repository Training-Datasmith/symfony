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
namespace Symfony\Bundle\Framework_Bundle\Command;

use Symfony\Component\Console\Attribute\As_Command;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\Input_Interface;
use Symfony\Component\Console\Output\Output_Interface;
use Symfony\Component\Console\Style\Symfony_Style;
/**
 * List available cache pools.
 *
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
#[As_Command(name: 'cache:pool:list', description: 'List available cache pools')]
final class Cache_Pool_List_Command extends Command
{
    /**
     * @param string[] $poolNames
     */
    public function __construct(private readonly array $pool_names)
    {
        parent::__construct();
    }
    protected function configure(): void
    {
        $this->set_help(<<<'EOF'
        The <info>%command.name%</info> command lists all available cache pools.
        EOF);
    }
    protected function execute(Input_Interface $input, Output_Interface $output): int
    {
        $io = new Symfony_Style($input, $output);
        $io->table(['Pool name'], array_map(static fn(string $pool): array => [$pool], $this->pool_names));
        return 0;
    }
}