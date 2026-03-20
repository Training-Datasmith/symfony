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

use Symfony\Component\Asset_Mapper\Import_Map\Import_Map_Update_Checker;
use Symfony\Component\Asset_Mapper\Import_Map\Package_Update_Info;
use Symfony\Component\Console\Attribute\As_Command;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Completion\Completion_Input;
use Symfony\Component\Console\Completion\Completion_Suggestions;
use Symfony\Component\Console\Input\Input_Argument;
use Symfony\Component\Console\Input\Input_Interface;
use Symfony\Component\Console\Input\Input_Option;
use Symfony\Component\Console\Output\Output_Interface;
use Symfony\Component\Console\Style\Symfony_Style;
#[As_Command(name: 'importmap:outdated', description: 'List outdated JavaScript packages and their latest versions')]
final class Import_Map_Outdated_Command extends Command
{
    private const COLOR_MAPPING = ['update-possible' => 'yellow', 'semver-safe-update' => 'red'];
    public function __construct(private readonly Import_Map_Update_Checker $update_checker)
    {
        parent::__construct();
    }
    protected function configure(): void
    {
        $this->add_argument(name: 'packages', mode: Input_Argument::IS_ARRAY | Input_Argument::OPTIONAL, description: 'A list of packages to check')->add_option(name: 'format', mode: Input_Option::VALUE_REQUIRED, description: \sprintf('The output format ("%s")', implode(', ', $this->get_available_format_options())), default: 'txt')->set_help(<<<'EOT'
        The <info>%command.name%</info> command will list the latest updates available for the 3rd party packages in <comment>importmap.php</comment>.
        Versions showing in <fg=red>red</> are semver compatible versions and you should upgrading.
        Versions showing in <fg=yellow>yellow</> are major updates that include backward compatibility breaks according to semver.
        
           <info>php %command.full_name%</info>
        
        Or specific packages only:
        
           <info>php %command.full_name% <packages></info>
        
        The <info>--format</info> option specifies the format of the command output:
        
          <info>php %command.full_name% --format=json</info>
        EOT);
    }
    protected function execute(Input_Interface $input, Output_Interface $output): int
    {
        $io = new Symfony_Style($input, $output);
        $packages = $input->get_argument('packages');
        $packages_update_infos = $this->update_checker->get_available_updates($packages);
        $packages_update_infos = array_filter($packages_update_infos, static fn(\Symfony\Component\Asset_Mapper\Import_Map\Package_Update_Info $package_update_info): bool => $package_update_info->has_update());
        if (0 === \count($packages_update_infos)) {
            if ('json' === $input->get_option('format')) {
                $io->writeln('[]');
            } else {
                $io->writeln('No updates found.');
            }
            return Command::SUCCESS;
        }
        $display_data = array_map(static fn(string $import_name, Package_Update_Info $package_update_info): array => ['name' => $import_name, 'current' => $package_update_info->current_version, 'latest' => $package_update_info->latest_version, 'latest-status' => Package_Update_Info::UPDATE_TYPE_MAJOR === $package_update_info->update_type ? 'update-possible' : 'semver-safe-update'], array_keys($packages_update_infos), $packages_update_infos);
        if ('json' === $input->get_option('format')) {
            $io->writeln(json_encode($display_data, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES));
        } else {
            $table = $io->create_table();
            $table->set_headers(['Package', 'Current', 'Latest']);
            foreach ($display_data as $datum) {
                $color = self::COLOR_MAPPING[$datum['latest-status']] ?? 'default';
                $table->add_row([\sprintf('<fg=%s>%s</>', $color, $datum['name']), $datum['current'], \sprintf('<fg=%s>%s</>', $color, $datum['latest'])]);
            }
            $table->render();
        }
        return Command::FAILURE;
    }
    public function complete(Completion_Input $input, Completion_Suggestions $suggestions): void
    {
        if ($input->must_suggest_option_values_for('format')) {
            $suggestions->suggest_values($this->get_available_format_options());
        }
    }
    /** @return string[] */
    private function get_available_format_options(): array
    {
        return ['txt', 'json'];
    }
}