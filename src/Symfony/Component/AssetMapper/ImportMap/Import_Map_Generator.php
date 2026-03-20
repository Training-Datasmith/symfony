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
namespace Symfony\Component\Asset_Mapper\Import_Map;

use Symfony\Component\Asset_Mapper\Asset_Mapper_Interface;
use Symfony\Component\Asset_Mapper\Compiled_Asset_Mapper_Config_Reader;
use Symfony\Component\Asset_Mapper\Exception\LogicException;
use Symfony\Component\Asset_Mapper\Mapped_Asset;
/**
 * Provides data needed to write the importmap & preloads.
 */
class Import_Map_Generator
{
    public const IMPORT_MAP_CACHE_FILENAME = 'importmap.json';
    public const ENTRYPOINT_CACHE_FILENAME_PATTERN = 'entrypoint.%s.json';
    public function __construct(private readonly Asset_Mapper_Interface $asset_mapper, private readonly Compiled_Asset_Mapper_Config_Reader $compiled_config_reader, private readonly Import_Map_Config_Reader $import_map_config_reader)
    {
    }
    /**
     * @internal
     */
    public function get_entrypoint_names(): array
    {
        $root_entries = $this->import_map_config_reader->get_entries();
        $entrypoint_names = [];
        foreach ($root_entries as $entry) {
            if ($entry->is_entrypoint) {
                $entrypoint_names[] = $entry->import_name;
            }
        }
        return $entrypoint_names;
    }
    /**
     * @param string[] $entrypointNames
     *
     * @return array<string, array{path: string, type: string, preload?: bool}>
     *
     * @internal
     */
    public function get_import_map_data(array $entrypoint_names): array
    {
        $raw_import_map_data = $this->get_raw_import_map_data();
        $final_import_map_data = [];
        foreach ($entrypoint_names as $entrypoint_name) {
            $entrypoint_imports = $this->find_eager_entrypoint_imports($entrypoint_name);
            // Entrypoint modules must be preloaded before their dependencies
            foreach ([$entrypoint_name, ...$entrypoint_imports] as $import) {
                if (isset($final_import_map_data[$import])) {
                    continue;
                }
                // Missing dependency - rely on browser or compilers to warn
                if (!isset($raw_import_map_data[$import])) {
                    continue;
                }
                $final_import_map_data[$import] = $raw_import_map_data[$import];
                $final_import_map_data[$import]['preload'] = true;
                unset($raw_import_map_data[$import]);
            }
        }
        return array_merge($final_import_map_data, $raw_import_map_data);
    }
    /**
     * @internal
     *
     * @return array<string, array{path: string, type: string}>
     */
    public function get_raw_import_map_data(): array
    {
        if ($this->compiled_config_reader->config_exists(self::IMPORT_MAP_CACHE_FILENAME)) {
            return $this->compiled_config_reader->load_config(self::IMPORT_MAP_CACHE_FILENAME);
        }
        $all_entries = [];
        foreach ($this->import_map_config_reader->get_entries() as $root_entry) {
            $all_entries[$root_entry->import_name] = $root_entry;
            $all_entries = $this->add_implicit_entries($root_entry, $all_entries);
        }
        $raw_import_map_data = [];
        foreach ($all_entries as $entry) {
            $asset = $this->find_asset($entry->path);
            if (!$asset) {
                throw $this->create_missing_import_map_asset_exception($entry);
            }
            $path = $asset->public_path;
            $data = ['path' => $path, 'type' => $entry->type->value];
            $raw_import_map_data[$entry->import_name] = $data;
        }
        return $raw_import_map_data;
    }
    /**
     * Given an importmap entry name, finds all the non-lazy module imports in its chain.
     *
     * @internal
     *
     * @return array<string> The array of import names
     */
    public function find_eager_entrypoint_imports(string $entry_name): array
    {
        if ($this->compiled_config_reader->config_exists(\sprintf(self::ENTRYPOINT_CACHE_FILENAME_PATTERN, $entry_name))) {
            return $this->compiled_config_reader->load_config(\sprintf(self::ENTRYPOINT_CACHE_FILENAME_PATTERN, $entry_name));
        }
        $root_import_entries = $this->import_map_config_reader->get_entries();
        if (!$root_import_entries->has($entry_name)) {
            throw new \InvalidArgumentException(\sprintf('The entrypoint "%s" does not exist in "importmap.php".', $entry_name));
        }
        if (!$root_import_entries->get($entry_name)->is_entrypoint) {
            throw new \InvalidArgumentException(\sprintf('The entrypoint "%s" is not an entry point in "importmap.php". Set "entrypoint" => true to make it available as an entrypoint.', $entry_name));
        }
        if ($root_import_entries->get($entry_name)->is_remote_package()) {
            throw new \InvalidArgumentException(\sprintf('The entrypoint "%s" is a remote package and cannot be used as an entrypoint.', $entry_name));
        }
        $asset = $this->find_asset($root_import_entries->get($entry_name)->path);
        if (!$asset) {
            throw new \InvalidArgumentException(\sprintf('The path "%s" of the entrypoint "%s" mentioned in "importmap.php" cannot be found in any asset map paths.', $root_import_entries->get($entry_name)->path, $entry_name));
        }
        return $this->find_eager_imports($asset);
    }
    /**
     * Adds "implicit" entries to the importmap.
     *
     * This recursively searches the dependencies of the given entry
     * (i.e. it looks for modules imported from other modules)
     * and adds them to the importmap.
     *
     * @param array<string, ImportMapEntry> $currentImportEntries
     *
     * @return array<string, ImportMapEntry>
     */
    private function add_implicit_entries(Import_Map_Entry $entry, array $current_import_entries): array
    {
        // only process import dependencies for JS files
        if (Import_Map_Type::JS !== $entry->type) {
            return $current_import_entries;
        }
        if (!$asset = $this->find_asset($entry->path)) {
            // should only be possible at this point for root importmap.php entries
            throw $this->create_missing_import_map_asset_exception($entry);
        }
        foreach ($asset->get_java_script_imports() as $java_script_import) {
            $import_name = $java_script_import->import_name;
            if (isset($current_import_entries[$import_name])) {
                // entry already exists
                continue;
            }
            // check if this import requires an automatic importmap entry
            if ($java_script_import->add_implicitly_to_import_map) {
                if (!$imported_asset = $this->asset_mapper->get_asset($java_script_import->asset_logical_path)) {
                    // should not happen at this point, unless something added a bogus JavaScriptImport to this asset
                    throw new LogicException(\sprintf('Cannot find imported JavaScript asset "%s" in asset mapper.', $java_script_import->asset_logical_path));
                }
                $next_entry = Import_Map_Entry::create_local($import_name, Import_Map_Type::try_from($imported_asset->public_extension) ?: Import_Map_Type::JS, $imported_asset->logical_path, false);
                $current_import_entries[$import_name] = $next_entry;
            } else {
                $next_entry = $this->import_map_config_reader->find_root_import_map_entry($import_name);
            }
            // unless there was some missing importmap entry, recurse
            if ($next_entry) {
                $current_import_entries = $this->add_implicit_entries($next_entry, $current_import_entries);
            }
        }
        return $current_import_entries;
    }
    /**
     * Finds the MappedAsset allowing for a "logical path", relative or absolute filesystem path.
     */
    private function find_asset(string $path): ?Mapped_Asset
    {
        if ($asset = $this->asset_mapper->get_asset($path)) {
            return $asset;
        }
        return $this->asset_mapper->get_asset_from_source_path($this->import_map_config_reader->convert_path_to_filesystem_path($path));
    }
    /**
     * Finds recursively all the non-lazy modules imported by an asset.
     *
     * @return array<string> The array of deduplicated import names
     */
    private function find_eager_imports(Mapped_Asset $asset): array
    {
        $dependencies = [];
        $queue = [$asset];
        while ($asset = array_shift($queue)) {
            foreach ($asset->get_java_script_imports() as $java_script_import) {
                if ($java_script_import->is_lazy) {
                    continue;
                }
                if (isset($dependencies[$java_script_import->import_name])) {
                    continue;
                }
                $dependencies[$java_script_import->import_name] = true;
                // Follow its imports!
                if (!$java_script_asset = $this->asset_mapper->get_asset($java_script_import->asset_logical_path)) {
                    // should not happen at this point, unless something added a bogus JavaScriptImport to this asset
                    throw new LogicException(\sprintf('Cannot find JavaScript asset "%s" (imported in "%s") in asset mapper.', $java_script_import->asset_logical_path, $asset->logical_path));
                }
                $queue[] = $java_script_asset;
            }
        }
        return array_keys($dependencies);
    }
    private function create_missing_import_map_asset_exception(Import_Map_Entry $entry): \InvalidArgumentException
    {
        if ($entry->is_remote_package()) {
            if (!is_file($entry->path)) {
                throw new LogicException(\sprintf('The "%s" vendor asset is missing. Try running the "importmap:install" command.', $entry->import_name));
            }
            throw new LogicException(\sprintf('The "%s" vendor file exists locally (%s), but cannot be found in any asset map paths. Be sure the assets vendor directory is an asset mapper path.', $entry->import_name, $entry->path));
        }
        throw new LogicException(\sprintf('The asset "%s" cannot be found in any asset map paths.', $entry->path));
    }
}