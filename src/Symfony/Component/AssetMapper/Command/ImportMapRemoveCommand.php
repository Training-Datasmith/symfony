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

use Symfony\Component\Asset_Mapper\Import_Map\Import_Map_Manager;
use Symfony\Component\Console\Attribute\As_Command;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\Input_Argument;
use Symfony\Component\Console\Input\Input_Interface;
use Symfony\Component\Console\Output\Output_Interface;
use Symfony\Component\Console\Style\Symfony_Style;
/**
 * @author Kévin Dunglas <kevin@dunglas.dev>
 */
#[As_Command(name: 'importmap:remove', description: 'Remove JavaScript packages')]
final class Import_Map_Remove_Command extends Command
{
    public function __construct(protected readonly Import_Map_Manager $import_map_manager)
    {
        parent::__construct();
    }
    protected function configure(): void
    {
        $this->add_argument('packages', Input_Argument::IS_ARRAY | Input_Argument::REQUIRED, 'The packages to remove')->set_help(<<<'EOT'
        The <info>%command.name%</info> command removes packages from the <comment>importmap.php</comment>.
        If a package was downloaded into your app, the downloaded file will also be removed.
        
        For example:
        
            <info>php %command.full_name% lodash</info>
        EOT);
    }
    protected function execute(Input_Interface $input, Output_Interface $output): int
    {
        $io = new Symfony_Style($input, $output);
        $package_list = $input->get_argument('packages');
        $this->import_map_manager->remove($package_list);
        if (1 === \count($package_list)) {
            $io->success(\sprintf('Removed "%s" from importmap.php.', $package_list[0]));
        } else {
            $io->success(\sprintf('Removed %d items from importmap.php.', \count($package_list)));
        }
        return Command::SUCCESS;
    }
}