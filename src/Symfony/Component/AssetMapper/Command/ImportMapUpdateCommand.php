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
namespace Symfony\Component\Asset_Mapper\Command;

use Symfony\Component\Asset_Mapper\Import_Map\Import_Map_Entry;
use Symfony\Component\Asset_Mapper\Import_Map\Import_Map_Manager;
use Symfony\Component\Asset_Mapper\Import_Map\Import_Map_Version_Checker;
use Symfony\Component\Console\Attribute\As_Command;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\Input_Argument;
use Symfony\Component\Console\Input\Input_Interface;
use Symfony\Component\Console\Output\Output_Interface;
use Symfony\Component\Console\Style\Symfony_Style;
/**
 * @author Kévin Dunglas <kevin@dunglas.dev>
 */
#[As_Command(name: 'importmap:update', description: 'Update JavaScript packages to their latest versions')]
final class Import_Map_Update_Command extends Command
{
    use Version_Problem_Command_Trait;
    public function __construct(private readonly Import_Map_Manager $import_map_manager, private readonly Import_Map_Version_Checker $import_map_version_checker)
    {
        parent::__construct();
    }
    protected function configure(): void
    {
        $this->add_argument('packages', Input_Argument::IS_ARRAY | Input_Argument::OPTIONAL, 'List of packages\' names')->set_help(<<<'EOT'
        The <info>%command.name%</info> command will update all from the 3rd part packages
        in <comment>importmap.php</comment> to their latest version, including downloaded packages.
        
           <info>php %command.full_name%</info>
        
        Or specific packages only:
        
            <info>php %command.full_name% <packages></info>
        EOT);
    }
    protected function execute(Input_Interface $input, Output_Interface $output): int
    {
        $packages = $input->get_argument('packages');
        $io = new Symfony_Style($input, $output);
        $updated_packages = $this->import_map_manager->update($packages);
        $this->render_version_problems($this->import_map_version_checker, $output);
        if (0 < \count($packages)) {
            $io->success(\sprintf('Updated %s package%s in importmap.php.', implode(', ', array_map(static fn(Import_Map_Entry $entry): string => $entry->import_name, $updated_packages)), 1 < \count($updated_packages) ? 's' : ''));
        } else {
            $io->success('Updated all packages in importmap.php.');
        }
        return Command::SUCCESS;
    }
}