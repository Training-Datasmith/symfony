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

use Symfony\Component\Asset_Mapper\Exception\RuntimeException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Var_Exporter\Var_Exporter;
/**
 * Reads/Writes the importmap.php file and returns the list of entries.
 *
 * @author Ryan Weaver <ryan@symfonycasts.com>
 */
class Import_Map_Config_Reader
{
    private Import_Map_Entries $root_import_map_entries;
    private readonly Filesystem $filesystem;
    public function __construct(private readonly string $import_map_config_path, private readonly Remote_Package_Storage $remote_package_storage)
    {
        $this->filesystem = new Filesystem();
    }
    public function get_entries(): Import_Map_Entries
    {
        if (isset($this->root_import_map_entries)) {
            return $this->root_import_map_entries;
        }
        $config_path = $this->import_map_config_path;
        $import_map_config = is_file($config_path) ? \Closure::bind(static fn() => include func_get_arg(0), null, null)($config_path) : [];
        $entries = new Import_Map_Entries();
        foreach ($import_map_config as $import_name => $data) {
            $valid_keys = ['path', 'version', 'type', 'entrypoint', 'package_specifier'];
            if ($invalid_keys = array_diff(array_keys($data), $valid_keys)) {
                throw new \InvalidArgumentException(\sprintf('The following keys are not valid for the importmap entry "%s": "%s". Valid keys are: "%s".', $import_name, implode('", "', $invalid_keys), implode('", "', $valid_keys)));
            }
            $type = Import_Map_Type::try_from($data['type'] ?? 'js') ?? Import_Map_Type::JS;
            $is_entrypoint = $data['entrypoint'] ?? false;
            if (isset($data['path'])) {
                if (isset($data['version'])) {
                    throw new RuntimeException(\sprintf('The importmap entry "%s" cannot have both a "path" and "version" option.', $import_name));
                }
                if (isset($data['package_specifier'])) {
                    throw new RuntimeException(\sprintf('The importmap entry "%s" cannot have both a "path" and "package_specifier" option.', $import_name));
                }
                $entries->add(Import_Map_Entry::create_local($import_name, $type, $data['path'], $is_entrypoint));
                continue;
            }
            $version = $data['version'] ?? null;
            if (null === $version) {
                throw new RuntimeException(\sprintf('The importmap entry "%s" must have either a "path" or "version" option.', $import_name));
            }
            $package_module_specifier = $data['package_specifier'] ?? $import_name;
            $entries->add($this->create_remote_entry($import_name, $type, $version, $package_module_specifier, $is_entrypoint));
        }
        return $this->root_import_map_entries = $entries;
    }
    public function write_entries(Import_Map_Entries $entries): void
    {
        $this->root_import_map_entries = $entries;
        $import_map_config = [];
        foreach ($entries as $entry) {
            $config = [];
            if ($entry->is_remote_package()) {
                $config['version'] = $entry->version;
                if ($entry->package_module_specifier !== $entry->import_name) {
                    $config['package_specifier'] = $entry->package_module_specifier;
                }
            } else {
                $config['path'] = $entry->path;
            }
            if (Import_Map_Type::JS !== $entry->type) {
                $config['type'] = $entry->type->value;
            }
            if ($entry->is_entrypoint) {
                $config['entrypoint'] = true;
            }
            $import_map_config[$entry->import_name] = $config;
        }
        $map = class_exists(Var_Exporter::class) ? Var_Exporter::export($import_map_config) : var_export($import_map_config, true);
        $this->filesystem->dump_file($this->import_map_config_path, <<<EOF
        <?php
        
        /**
         * Returns the importmap for this application.
         *
         * - "path" is a path inside the asset mapper system. Use the
         *     "debug:asset-map" command to see the full list of paths.
         *
         * - "entrypoint" (JavaScript only) set to true for any module that will
         *     be used as an "entrypoint" (and passed to the importmap() Twig function).
         *
         * The "importmap:require" command can be used to add new entries to this file.
         *
         * @return array<string, array{    // Import name as key, description of the imported file as value
         *     path: string,               // Logical, relative or absolute path to the file
         *     type?: 'js'|'css'|'json',   // Type of the file, defaults to 'js'
         *     entrypoint?: bool,          // Whether the file is an entrypoint, for 'js' only
         * }|array{
         *     version: string,            // Version of the remote package
         *     package_specifier?: string, // Remote "package-name/path" specifier, defaults to the import name
         *     type?: 'js'|'css'|'json',
         *     entrypoint?: bool,
         * }>
         */
        return {$map};
        
        EOF);
    }
    public function find_root_import_map_entry(string $module_name): ?Import_Map_Entry
    {
        $entries = $this->get_entries();
        return $entries->has($module_name) ? $entries->get($module_name) : null;
    }
    public function create_remote_entry(string $import_name, Import_Map_Type $type, string $version, string $package_module_specifier, bool $is_entrypoint): Import_Map_Entry
    {
        $path = $this->remote_package_storage->get_download_path($package_module_specifier, $type);
        return Import_Map_Entry::create_remote($import_name, $type, $path, $version, $package_module_specifier, $is_entrypoint);
    }
    /**
     * Converts the "path" string from an importmap entry to the filesystem path.
     *
     * The path may already be a filesystem path. But if it starts with ".",
     * then the path is relative and the root directory is prepended.
     */
    public function convert_path_to_filesystem_path(string $path): string
    {
        if (!str_starts_with($path, '.')) {
            return $path;
        }
        return Path::join($this->get_root_directory(), $path);
    }
    /**
     * Converts a filesystem path to a relative path that can be used in the importmap.
     *
     * If no relative path could be created - e.g. because the path is not in
     * the same directory/subdirectory as the root importmap.php file - null is returned.
     */
    public function convert_filesystem_path_to_path(string $filesystem_path): ?string
    {
        $root_import_map_dir = realpath($this->get_root_directory());
        $filesystem_path = realpath($filesystem_path);
        if (!str_starts_with($filesystem_path, $root_import_map_dir)) {
            return null;
        }
        // remove the root directory, prepend "./" & normalize slashes
        return './' . str_replace('\\', '/', substr($filesystem_path, \strlen($root_import_map_dir) + 1));
    }
    private function get_root_directory(): string
    {
        return \dirname($this->import_map_config_path);
    }
}