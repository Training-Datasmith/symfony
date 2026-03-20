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

use Symfony\Component\Asset_Mapper\Import_Map\Import_Map_Entries;
use Symfony\Component\Asset_Mapper\Import_Map\Import_Map_Entry;
use Symfony\Component\Asset_Mapper\Import_Map\Import_Map_Manager;
use Symfony\Component\Asset_Mapper\Import_Map\Import_Map_Version_Checker;
use Symfony\Component\Asset_Mapper\Import_Map\Package_Require_Options;
use Symfony\Component\Console\Attribute\As_Command;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\Input_Argument;
use Symfony\Component\Console\Input\Input_Interface;
use Symfony\Component\Console\Input\Input_Option;
use Symfony\Component\Console\Output\Output_Interface;
use Symfony\Component\Console\Style\Symfony_Style;
use Symfony\Component\Filesystem\Path;
/**
 * @author Kévin Dunglas <kevin@dunglas.dev>
 */
#[As_Command(name: 'importmap:require', description: 'Require JavaScript packages')]
final class Import_Map_Require_Command extends Command
{
    use Version_Problem_Command_Trait;
    public function __construct(private readonly Import_Map_Manager $import_map_manager, private readonly Import_Map_Version_Checker $import_map_version_checker, private readonly string $project_dir)
    {
        parent::__construct();
    }
    protected function configure(): void
    {
        $this->add_argument('packages', Input_Argument::IS_ARRAY | Input_Argument::REQUIRED, 'The packages to add')->add_option('entrypoint', null, Input_Option::VALUE_NONE, 'Make the packages an entrypoint?')->add_option('path', null, Input_Option::VALUE_REQUIRED, 'The local path where the package lives relative to the project root')->add_option('dry-run', null, Input_Option::VALUE_NONE, 'Simulate the installation of the packages')->set_help(<<<'EOT'
        The <info>%command.name%</info> command adds packages to <comment>importmap.php</comment> usually
        by finding a CDN URL for the given package and version.
        
        For example:
        
            <info>php %command.full_name% lodash</info>
            <info>php %command.full_name% "lodash@^4.15"</info>
        
        You can also require specific paths of a package:
        
            <info>php %command.full_name% "chart.js/auto"</info>
        
        Or require one package/file, but alias its name in your import map:
        
            <info>php %command.full_name% "vue/dist/vue.esm-bundler.js=vue"</info>
        
        Sometimes, a package may require other packages and multiple new items may be added
        to the import map.
        
        You can also require multiple packages at once:
        
            <info>php %command.full_name% "lodash@^4.15" "@hotwired/stimulus"</info>
        
        To add an importmap entry pointing to a local file, use the <info>path</info> option:
        
            <info>php %command.full_name% "any_module_name" --path=./assets/some_file.js</info>
        
        To simulate the installation, use the <info>--dry-run</info> option:
        
            <info>php %command.full_name% "any_module_name" --dry-run -v</info>
        
        When this option is enabled, this command does not perform any write operations to the filesystem.
        EOT);
    }
    protected function execute(Input_Interface $input, Output_Interface $output): int
    {
        $io = new Symfony_Style($input, $output);
        $package_list = $input->get_argument('packages');
        $path = null;
        if ($input->get_option('path')) {
            if (\count($package_list) > 1) {
                $io->error('The "--path" option can only be used when you require a single package.');
                return Command::FAILURE;
            }
            $path = $input->get_option('path');
        }
        if ($input->get_option('dry-run')) {
            $io->writeln(['', '<comment>[DRY-RUN]</comment> No changes will apply to the importmap configuration.', '']);
        }
        $packages = [];
        foreach ($package_list as $package_name) {
            $parts = Import_Map_Manager::parse_package_name($package_name);
            if (null === $parts) {
                $io->error(\sprintf('Package "%s" is not a valid package name format. Use the format PACKAGE@VERSION - e.g. "lodash" or "lodash@^4"', $package_name));
                return Command::FAILURE;
            }
            $packages[] = new Package_Require_Options($parts['package'], $parts['version'] ?? null, $parts['alias'] ?? null, $path, $input->get_option('entrypoint'));
        }
        if ($input->get_option('dry-run')) {
            $new_packages = $this->import_map_manager->require_packages($packages, new Import_Map_Entries());
        } else {
            $new_packages = $this->import_map_manager->require($packages);
        }
        $this->render_version_problems($this->import_map_version_checker, $output);
        $new_package_names = array_map(static fn(Import_Map_Entry $package): string => $package->import_name, $new_packages);
        if (1 === \count($new_packages)) {
            $messages = [\sprintf('Package "%s" added to importmap.php.', $new_package_names[0])];
        } else {
            $messages = [\sprintf('%d new items (%s) added to the importmap.php!', \count($new_packages), implode(', ', $new_package_names))];
        }
        if ($io->is_verbose()) {
            $io->table(['Package', 'Version', 'Path'], array_map(fn(Import_Map_Entry $package): array => [$package->import_name, $package->version ?? '-', Path::make_relative($package->path, $this->project_dir)], $new_packages));
        }
        if (1 === \count($new_packages)) {
            $messages[] = \sprintf('Use the new package normally by importing "%s".', $new_packages[0]->import_name);
        }
        $io->success($messages);
        if ($input->get_option('dry-run')) {
            $io->writeln(['<comment>[DRY-RUN]</comment> No changes applied to the importmap configuration.', '']);
        }
        return Command::SUCCESS;
    }
}