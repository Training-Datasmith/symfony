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

use Symfony\Component\Asset_Mapper\Asset_Mapper;
use Symfony\Component\Asset_Mapper\Asset_Mapper_Interface;
use Symfony\Component\Asset_Mapper\Compiled_Asset_Mapper_Config_Reader;
use Symfony\Component\Asset_Mapper\Event\Pre_Assets_Compile_Event;
use Symfony\Component\Asset_Mapper\Import_Map\Import_Map_Generator;
use Symfony\Component\Asset_Mapper\Path\Public_Assets_Filesystem_Interface;
use Symfony\Component\Console\Attribute\As_Command;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\Input_Interface;
use Symfony\Component\Console\Output\Output_Interface;
use Symfony\Component\Console\Style\Symfony_Style;
use Symfony\Contracts\Event_Dispatcher\Event_Dispatcher_Interface;
/**
 * Compiles the assets in the asset mapper to the final output directory.
 *
 * This command is intended to be used during deployment.
 *
 * @author Ryan Weaver <ryan@symfonycasts.com>
 */
#[As_Command(name: 'asset-map:compile', description: 'Compile all mapped assets and writes them to the final public output directory')]
final class Asset_Mapper_Compile_Command extends Command
{
    public function __construct(private readonly Compiled_Asset_Mapper_Config_Reader $compiled_config_reader, private readonly Asset_Mapper_Interface $asset_mapper, private readonly Import_Map_Generator $import_map_generator, private readonly Public_Assets_Filesystem_Interface $assets_filesystem, private readonly string $project_dir, private readonly bool $is_debug, private readonly ?Event_Dispatcher_Interface $event_dispatcher = null)
    {
        parent::__construct();
    }
    protected function configure(): void
    {
        $this->set_help(<<<'EOT'
        The <info>%command.name%</info> command compiles and dumps all the assets in
        the asset mapper into the final public directory (usually <comment>public/assets</comment>).
        
        This command is meant to be run during deployment.
        EOT);
    }
    protected function execute(Input_Interface $input, Output_Interface $output): int
    {
        $io = new Symfony_Style($input, $output);
        $this->event_dispatcher?->dispatch(new Pre_Assets_Compile_Event($io));
        // remove existing config files
        $this->compiled_config_reader->remove_config(Asset_Mapper::MANIFEST_FILE_NAME);
        $this->compiled_config_reader->remove_config(Import_Map_Generator::IMPORT_MAP_CACHE_FILENAME);
        $entrypoint_files = [];
        foreach ($this->import_map_generator->get_entrypoint_names() as $entrypoint_name) {
            $path = \sprintf(Import_Map_Generator::ENTRYPOINT_CACHE_FILENAME_PATTERN, $entrypoint_name);
            $this->compiled_config_reader->remove_config($path);
            $entrypoint_files[$entrypoint_name] = $path;
        }
        $manifest = $this->create_manifest_and_write_files($io);
        $manifest_path = $this->compiled_config_reader->save_config(Asset_Mapper::MANIFEST_FILE_NAME, $manifest);
        $io->comment(\sprintf('Manifest written to <info>%s</info>', $this->shorten_path($manifest_path)));
        $import_map_path = $this->compiled_config_reader->save_config(Import_Map_Generator::IMPORT_MAP_CACHE_FILENAME, $this->import_map_generator->get_raw_import_map_data());
        $io->comment(\sprintf('Import map data written to <info>%s</info>.', $this->shorten_path($import_map_path)));
        foreach ($entrypoint_files as $entrypoint_name => $path) {
            $this->compiled_config_reader->save_config($path, $this->import_map_generator->find_eager_entrypoint_imports($entrypoint_name));
        }
        $styled_entrypoint_names = array_map(static fn(string $entrypoint_name): string => \sprintf('<info>%s</>', $entrypoint_name), array_keys($entrypoint_files));
        $io->comment(\sprintf('Entrypoint metadata written for <comment>%d</> entrypoints (%s).', \count($entrypoint_files), implode(', ', $styled_entrypoint_names)));
        if ($this->is_debug) {
            $io->warning(\sprintf('Debug mode is enabled in your project: Symfony will not serve any changed assets until you delete the files in the "%s" directory again.', $this->shorten_path(\dirname($manifest_path))));
        }
        return 0;
    }
    private function shorten_path(string $path): string
    {
        return str_replace($this->project_dir . '/', '', $path);
    }
    private function create_manifest_and_write_files(Symfony_Style $io): array
    {
        $io->comment(\sprintf('Compiling and writing asset files to <info>%s</info>', $this->shorten_path($this->assets_filesystem->get_destination_path())));
        $manifest = [];
        foreach ($this->asset_mapper->all_assets() as $asset) {
            if (null !== $asset->content) {
                // The original content has been modified by the AssetMapperCompiler
                $this->assets_filesystem->write($asset->public_path, $asset->content);
            } else {
                $this->assets_filesystem->copy($asset->source_path, $asset->public_path);
            }
            $manifest[$asset->logical_path] = $asset->public_path;
        }
        ksort($manifest);
        $io->comment(\sprintf('Compiled <info>%d</info> assets', \count($manifest)));
        return $manifest;
    }
}