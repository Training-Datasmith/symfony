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

use Symfony\Component\Asset_Mapper\Import_Map\Import_Map_Auditor;
use Symfony\Component\Asset_Mapper\Import_Map\Import_Map_Package_Audit_Vulnerability;
use Symfony\Component\Console\Attribute\As_Command;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Completion\Completion_Input;
use Symfony\Component\Console\Completion\Completion_Suggestions;
use Symfony\Component\Console\Input\Input_Interface;
use Symfony\Component\Console\Input\Input_Option;
use Symfony\Component\Console\Output\Output_Interface;
use Symfony\Component\Console\Style\Symfony_Style;
#[As_Command(name: 'importmap:audit', description: 'Check for security vulnerability advisories for dependencies')]
class Import_Map_Audit_Command extends Command
{
    private const SEVERITY_COLORS = ['critical' => 'red', 'high' => 'red', 'medium' => 'yellow', 'low' => 'default', 'unknown' => 'default'];
    private Symfony_Style $io;
    public function __construct(private readonly Import_Map_Auditor $import_map_auditor)
    {
        parent::__construct();
    }
    protected function configure(): void
    {
        $this->add_option(name: 'format', mode: Input_Option::VALUE_REQUIRED, description: \sprintf('The output format ("%s")', implode(', ', $this->get_available_format_options())), default: 'txt')->set_help(<<<'EOT'
        The <info>--format</info> option specifies the format of the command output:
        
          <info>php %command.full_name% --format=json</info>
        EOT);
    }
    protected function initialize(Input_Interface $input, Output_Interface $output): void
    {
        $this->io = new Symfony_Style($input, $output);
    }
    protected function execute(Input_Interface $input, Output_Interface $output): int
    {
        $format = $input->get_option('format');
        $audit = $this->import_map_auditor->audit();
        return match ($format) {
            'txt' => $this->display_txt($audit),
            'json' => $this->display_json($audit),
            default => throw new \InvalidArgumentException(\sprintf('Supported formats are "%s".', implode('", "', $this->get_available_format_options()))),
        };
    }
    private function display_txt(array $audit): int
    {
        $rows = [];
        $packages_without_version = [];
        $vulnerabilities_count = array_map(static fn(): int => 0, self::SEVERITY_COLORS);
        foreach ($audit as $package_audit) {
            if (!$package_audit->version) {
                $packages_without_version[] = $package_audit->package;
            }
            foreach ($package_audit->vulnerabilities as $vulnerability) {
                $rows[] = [\sprintf('<fg=%s>%s</>', self::SEVERITY_COLORS[$vulnerability->severity] ?? 'default', ucfirst((string) $vulnerability->severity)), $vulnerability->summary, $package_audit->package, $package_audit->version ?? 'n/a', $vulnerability->first_patched_version ?? 'n/a', $vulnerability->url];
                ++$vulnerabilities_count[$vulnerability->severity];
            }
        }
        $packages_count = \count($audit);
        $packages_without_version_count = \count($packages_without_version);
        if (!$rows && !$packages_without_version_count) {
            $this->io->info('No vulnerabilities found.');
            return self::SUCCESS;
        }
        if ($rows) {
            $table = $this->io->create_table();
            $table->set_headers(['Severity', 'Title', 'Package', 'Version', 'Patched in', 'More info']);
            $table->add_rows($rows);
            $table->render();
            $this->io->new_line();
        }
        $this->io->text(\sprintf('%d package%s found: %d audited / %d skipped', $packages_count, 1 === $packages_count ? '' : 's', $packages_count - $packages_without_version_count, $packages_without_version_count));
        if (0 < $packages_without_version_count) {
            $this->io->warning(\sprintf('Unable to retrieve versions for package%s: %s', 1 === $packages_without_version_count ? '' : 's', implode(', ', $packages_without_version)));
        }
        if ([] !== $rows) {
            $vulnerability_count = 0;
            $vulnerability_summary = [];
            foreach ($vulnerabilities_count as $severity => $count) {
                if (!$count) {
                    continue;
                }
                $vulnerability_summary[] = \sprintf('%d %s', $count, ucfirst((string) $severity));
                $vulnerability_count += $count;
            }
            $this->io->text(\sprintf('%d vulnerabilit%s found: %s', $vulnerability_count, 1 === $vulnerability_count ? 'y' : 'ies', implode(' / ', $vulnerability_summary)));
        }
        return self::FAILURE;
    }
    private function display_json(array $audit): int
    {
        $vulnerabilities_count = array_map(static fn(): int => 0, self::SEVERITY_COLORS);
        $json = ['packages' => [], 'summary' => $vulnerabilities_count];
        foreach ($audit as $package_audit) {
            $json['packages'][] = ['package' => $package_audit->package, 'version' => $package_audit->version, 'vulnerabilities' => array_map(static fn(Import_Map_Package_Audit_Vulnerability $v): array => ['ghsa_id' => $v->ghsa_id, 'cve_id' => $v->cve_id, 'url' => $v->url, 'summary' => $v->summary, 'severity' => $v->severity, 'vulnerable_version_range' => $v->vulnerable_version_range, 'first_patched_version' => $v->first_patched_version], $package_audit->vulnerabilities)];
            foreach ($package_audit->vulnerabilities as $vulnerability) {
                ++$json['summary'][$vulnerability->severity];
            }
        }
        $this->io->write(json_encode($json));
        return 0 < array_sum($json['summary']) ? self::FAILURE : self::SUCCESS;
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