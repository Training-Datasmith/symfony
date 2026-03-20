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

use Symfony\Component\Asset_Mapper\Asset_Mapper_Interface;
use Symfony\Component\Asset_Mapper\Asset_Mapper_Repository;
use Symfony\Component\Console\Attribute\As_Command;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\Input_Argument;
use Symfony\Component\Console\Input\Input_Interface;
use Symfony\Component\Console\Input\Input_Option;
use Symfony\Component\Console\Output\Output_Interface;
use Symfony\Component\Console\Style\Symfony_Style;
/**
 * Outputs all the assets in the asset mapper.
 *
 * @author Ryan Weaver <ryan@symfonycasts.com>
 */
#[As_Command(name: 'debug:asset-map', description: 'Output all mapped assets')]
final class Debug_Asset_Mapper_Command extends Command
{
    private bool $did_shorten_paths = false;
    public function __construct(private readonly Asset_Mapper_Interface $asset_mapper, private readonly Asset_Mapper_Repository $asset_mapper_repository, private readonly string $project_dir)
    {
        parent::__construct();
    }
    protected function configure(): void
    {
        $this->add_argument('name', Input_Argument::OPTIONAL, 'An asset name (or a path) to search for (e.g. "app")')->add_option('ext', null, Input_Option::VALUE_REQUIRED, 'Filter assets by extension (e.g. "css")', null, ['js', 'css', 'json'])->add_option('full', null, null, 'Whether to show the full paths')->add_option('vendor', null, Input_Option::VALUE_NEGATABLE, 'Only show assets from vendor packages')->set_help(<<<'EOT'
        The <info>%command.name%</info> command displays information about the Asset
        Mapper for debugging purposes.
        
        To list all configured paths (with local paths and their namespace prefixes) and
        all mapped assets (with their logical path and filesystem path), run:
        
          <info>php %command.full_name%</info>
        
        You can filter the results by providing a name to search for in the asset name
        or path:
        
          <info>php %command.full_name% bootstrap.js</info>
          <info>php %command.full_name% style/</info>
        
        To filter the assets by extension, use the <info>--ext</info> option:
        
          <info>php %command.full_name% --ext=css</info>
        
        To show only assets from vendor packages, use the <info>--vendor</info> option:
        
          <info>php %command.full_name% --vendor</info>
        
        To exclude assets from vendor packages, use the <info>--no-vendor</info> option:
        
          <info>php %command.full_name% --no-vendor</info>
        
        To see the full paths, use the <info>--full</info> option:
        
            <info>php %command.full_name% --full</info>
        
        EOT);
    }
    protected function execute(Input_Interface $input, Output_Interface $output): int
    {
        $io = new Symfony_Style($input, $output);
        $name = $input->get_argument('name');
        $extension_filter = $input->get_option('ext');
        $vendor_filter = $input->get_option('vendor');
        if (!$extension_filter) {
            $io->section($name ? 'Matched Paths' : 'Asset Mapper Paths');
            $path_rows = [];
            foreach ($this->asset_mapper_repository->all_directories() as $path => $namespace) {
                $path = $this->relativize_path($path);
                if (!$input->get_option('full')) {
                    $path = $this->shorten_path($path);
                }
                if ($name && !str_contains($path, (string) $name) && !str_contains((string) $namespace, (string) $name)) {
                    continue;
                }
                $path_rows[] = [$path, $namespace];
            }
            uasort($path_rows, static fn(array $a, array $b): int => [(bool) $a[1], ...$a] <=> [(bool) $b[1], ...$b]);
            if ($path_rows) {
                $io->table(['Path', 'Namespace prefix'], $path_rows);
            } else {
                $io->warning('No paths found.');
            }
        }
        $io->section($name ? 'Matched Assets' : 'Mapped Assets');
        $rows = $this->search_assets($name, $extension_filter, $vendor_filter);
        if ($rows) {
            if (!$input->get_option('full')) {
                $rows = array_map(fn(array $row): array => [$this->shorten_path($row[0]), $this->shorten_path($row[1])], $rows);
            }
            uasort($rows, static fn(array $a, array $b): int => [$a] <=> [$b]);
            $io->table(['Logical Path', 'Filesystem Path'], $rows);
            if ($this->did_shorten_paths) {
                $io->note('To see the full paths, re-run with the --full option.');
            }
        } else {
            $io->warning('No assets found.');
        }
        return 0;
    }
    /**
     * @return list<array{0:string, 1:string}>
     */
    private function search_assets(?string $name, ?string $extension, ?bool $vendor): array
    {
        $rows = [];
        foreach ($this->asset_mapper->all_assets() as $asset) {
            if ($extension && $extension !== $asset->public_extension) {
                continue;
            }
            if (null !== $vendor && $vendor !== $asset->is_vendor) {
                continue;
            }
            if ($name && !str_contains($asset->logical_path, $name) && !str_contains($asset->source_path, $name)) {
                continue;
            }
            $logical_path = $asset->logical_path;
            $source_path = $this->relativize_path($asset->source_path);
            $rows[] = [$logical_path, $source_path];
        }
        return $rows;
    }
    private function relativize_path(string $path): string
    {
        return str_replace($this->project_dir . '/', '', $path);
    }
    private function shorten_path(string $path): string
    {
        $limit = 50;
        if (\strlen($path) <= $limit) {
            return $path;
        }
        $this->did_shorten_paths = true;
        $limit = floor(($limit - 3) / 2);
        return substr($path, 0, $limit) . '...' . substr($path, -$limit);
    }
}