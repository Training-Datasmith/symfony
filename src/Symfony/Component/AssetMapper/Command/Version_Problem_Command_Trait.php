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

use Symfony\Component\Asset_Mapper\Import_Map\Import_Map_Version_Checker;
use Symfony\Component\Console\Output\Output_Interface;
/**
 * @internal
 */
trait Version_Problem_Command_Trait
{
    private function render_version_problems(Import_Map_Version_Checker $import_map_version_checker, Output_Interface $output): void
    {
        $problems = $import_map_version_checker->check_versions();
        foreach ($problems as $problem) {
            if (null === $problem->installed_version) {
                $output->writeln(\sprintf('[warning] <info>%s</info> requires <info>%s</info> but it is not in the importmap.php. You may need to run "php bin/console importmap:require %s".', $problem->package_name, $problem->dependency_package_name, $problem->dependency_package_name));
                continue;
            }
            $output->writeln(\sprintf('[warning] <info>%s</info> requires <info>%s</info>@<comment>%s</comment> but version <comment>%s</comment> is installed.', $problem->package_name, $problem->dependency_package_name, $problem->required_version_constraint, $problem->installed_version));
        }
    }
}