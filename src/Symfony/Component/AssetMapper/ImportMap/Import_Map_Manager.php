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
use Symfony\Component\Asset_Mapper\Import_Map\Resolver\Package_Resolver_Interface;
use Symfony\Component\Asset_Mapper\Mapped_Asset;
/**
 * @author Kévin Dunglas <kevin@dunglas.dev>
 * @author Ryan Weaver <ryan@symfonycasts.com>
 *
 * @final
 */
class Import_Map_Manager
{
    public function __construct(private readonly Asset_Mapper_Interface $asset_mapper, private readonly Import_Map_Config_Reader $import_map_config_reader, private readonly Remote_Package_Downloader $package_downloader, private readonly Package_Resolver_Interface $resolver)
    {
    }
    /**
     * Adds or updates packages.
     *
     * @param PackageRequireOptions[] $packages
     *
     * @return ImportMapEntry[]
     */
    public function require(array $packages): array
    {
        return $this->update_import_map_config(false, $packages, [], []);
    }
    /**
     * Removes packages.
     *
     * @param string[] $packages
     */
    public function remove(array $packages): void
    {
        $this->update_import_map_config(false, [], $packages, []);
    }
    /**
     * Updates either all existing packages or the specified ones to the latest version.
     *
     * @return ImportMapEntry[]
     */
    public function update(array $packages = []): array
    {
        return $this->update_import_map_config(true, [], [], $packages);
    }
    /**
     * @internal
     */
    public static function parse_package_name(string $package_name): ?array
    {
        // https://regex101.com/r/z1nj7P/1
        $regex = '/((?P<package>@?[^=@\n]+))(?:@(?P<version>[^=\s\n]+))?(?:=(?P<alias>[^\s\n]+))?/';
        if (!preg_match($regex, $package_name, $matches)) {
            return null;
        }
        if (isset($matches['version']) && '' === $matches['version']) {
            unset($matches['version']);
        }
        return $matches;
    }
    /**
     * @param PackageRequireOptions[] $packagesToRequire
     * @param string[]                $packagesToRemove
     *
     * @return ImportMapEntry[]
     */
    private function update_import_map_config(bool $update, array $packages_to_require, array $packages_to_remove, array $packages_to_update): array
    {
        $current_entries = $this->import_map_config_reader->get_entries();
        foreach ($packages_to_remove as $package_name) {
            if (!$current_entries->has($package_name)) {
                throw new \InvalidArgumentException(\sprintf('Package "%s" listed for removal was not found in "importmap.php".', $package_name));
            }
            $this->cleanup_package_files($current_entries->get($package_name));
            $current_entries->remove($package_name);
        }
        if ($update) {
            foreach ($current_entries as $entry) {
                $import_name = $entry->import_name;
                if (!$entry->is_remote_package()) {
                    continue;
                }
                if ($packages_to_update && !\in_array($import_name, $packages_to_update, true)) {
                    continue;
                }
                $packages_to_require[] = new Package_Require_Options($entry->package_module_specifier, null, $import_name, null, $entry->is_entrypoint);
                // remove it: then it will be re-added
                $this->cleanup_package_files($entry);
                $current_entries->remove($import_name);
            }
        }
        $new_entries = $this->require_packages($packages_to_require, $current_entries);
        $this->import_map_config_reader->write_entries($current_entries);
        $this->package_downloader->download_packages();
        return $new_entries;
    }
    /**
     * @internal
     *
     * Gets information about (and optionally downloads) the packages & updates the entries.
     *
     * Returns an array of the entries that were added.
     *
     * @param PackageRequireOptions[] $packagesToRequire
     */
    public function require_packages(array $packages_to_require, Import_Map_Entries $import_map_entries): array
    {
        if (!$packages_to_require) {
            return [];
        }
        $added_entries = [];
        // handle local packages
        foreach ($packages_to_require as $key => $require_options) {
            if (null === $require_options->path) {
                continue;
            }
            $path = $require_options->path;
            if (!$asset = $this->find_asset($path)) {
                throw new \LogicException(\sprintf('The path "%s" of the package "%s" cannot be found: either pass the logical name of the asset or a relative path starting with "./".', $require_options->path, $require_options->import_name));
            }
            // convert to a relative path (or fallback to the logical path)
            $path = $asset->logical_path;
            if (null !== $relative_path = $this->import_map_config_reader->convert_filesystem_path_to_path($asset->source_path)) {
                $path = $relative_path;
            }
            $new_entry = Import_Map_Entry::create_local($require_options->import_name, Import_Map_Type::try_from(pathinfo($path, \PATHINFO_EXTENSION)) ?? Import_Map_Type::JS, $path, $require_options->entrypoint);
            $import_map_entries->add($new_entry);
            $added_entries[] = $new_entry;
            unset($packages_to_require[$key]);
        }
        if (!$packages_to_require) {
            return $added_entries;
        }
        $resolved_packages = $this->resolver->resolve_packages($packages_to_require);
        foreach ($resolved_packages as $resolved_package) {
            $new_entry = $this->import_map_config_reader->create_remote_entry($resolved_package->require_options->import_name, $resolved_package->type, $resolved_package->version, $resolved_package->require_options->package_module_specifier, $resolved_package->require_options->entrypoint);
            $import_map_entries->add($new_entry);
            $added_entries[] = $new_entry;
        }
        return $added_entries;
    }
    private function cleanup_package_files(Import_Map_Entry $entry): void
    {
        $asset = $this->find_asset($entry->path);
        if ($asset && is_file($asset->source_path)) {
            @unlink($asset->source_path);
        }
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
}