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
namespace Symfony\Bundle\Security_Bundle\Command;

use Symfony\Component\Console\Attribute\As_Command;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\Input_Interface;
use Symfony\Component\Console\Input\Input_Option;
use Symfony\Component\Console\Output\Output_Interface;
use Symfony\Component\Console\Style\Symfony_Style;
use Symfony\Component\Security\Core\Dumper\Mermaid_Direction;
use Symfony\Component\Security\Core\Dumper\Mermaid_Dumper;
use Symfony\Component\Security\Core\Role\Role_Hierarchy_Interface;
/**
 * Command to dump the role hierarchy as a Mermaid flowchart.
 *
 * @author Damien Fernandes <damien.fernandes24@gmail.com>
 */
#[As_Command(name: 'debug:security:role-hierarchy', description: 'Dump the role hierarchy as a Mermaid flowchart')]
class Security_Role_Hierarchy_Dump_Command extends Command
{
    public function __construct(private readonly Role_Hierarchy_Interface $role_hierarchy)
    {
        parent::__construct();
    }
    protected function configure(): void
    {
        $this->set_definition([new Input_Option('direction', 'd', Input_Option::VALUE_REQUIRED, 'The direction of the flowchart [' . implode('|', array_column(Mermaid_Direction::cases(), 'value')) . ']', Mermaid_Direction::TOP_TO_BOTTOM->value, array_column(Mermaid_Direction::cases(), 'value'))])->set_help(<<<'USAGE'
        The <info>%command.name%</info> command dumps the role hierarchy in Mermaid format.
        
        <info>Mermaid</info>: %command.full_name% > roles.mmd
        <info>Mermaid with direction</info>: %command.full_name% --direction=BT > roles.mmd
        USAGE);
    }
    protected function execute(Input_Interface $input, Output_Interface $output): int
    {
        $io = new Symfony_Style($input, $output);
        $direction = $input->get_option('direction');
        if (!$direction = Mermaid_Direction::try_from($direction)) {
            $io->get_error_style()->writeln(\sprintf('<error>Invalid direction, available options are "%s"</error>', implode('"', array_column(Mermaid_Direction::cases(), 'value'))));
            return Command::FAILURE;
        }
        $dumper = new Mermaid_Dumper();
        foreach (explode("\n", $dumper->dump($this->role_hierarchy, $direction)) as $line) {
            $output->writeln($line, Output_Interface::OUTPUT_RAW);
        }
        return Command::SUCCESS;
    }
}