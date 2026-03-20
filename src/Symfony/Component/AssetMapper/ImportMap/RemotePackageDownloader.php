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

use Symfony\Component\Asset_Mapper\Import_Map\Resolver\Package_Resolver_Interface;
use Symfony\Component\Filesystem\Filesystem;
/**
 * @final
 */
class Remote_Package_Downloader
{
    private array $installed;
    private readonly Filesystem $filesystem;
    public function __construct(private readonly Remote_Package_Storage $remote_package_storage, private readonly Import_Map_Config_Reader $import_map_config_reader, private readonly Package_Resolver_Interface $package_resolver)
    {
        $this->filesystem = new Filesystem();
    }
    /**
     * Downloads all packages.
     *
     * @return string[] The downloaded packages
     */
    public function download_packages(?callable $progress_callback = null): array
    {
        try {
            $installed = $this->load_installed();
        } catch (\InvalidArgumentException) {
            $installed = [];
        }
        $entries = $this->import_map_config_reader->get_entries();
        $remote_entries_to_download = [];
        $new_installed = [];
        foreach ($entries as $entry) {
            if (!$entry->is_remote_package()) {
                continue;
            }
            // if the file exists at the correct version, skip it
            if (isset($installed[$entry->import_name]) && $installed[$entry->import_name]['version'] === $entry->version && $this->remote_package_storage->is_downloaded($entry) && $this->are_all_extra_files_downloaded($entry, $installed[$entry->import_name]['extraFiles'])) {
                $new_installed[$entry->import_name] = $installed[$entry->import_name];
                continue;
            }
            $remote_entries_to_download[$entry->import_name] = $entry;
        }
        if (!$remote_entries_to_download) {
            return [];
        }
        $contents = $this->package_resolver->download_packages($remote_entries_to_download, $progress_callback);
        $downloaded_packages = [];
        foreach ($remote_entries_to_download as $package => $entry) {
            if (!isset($contents[$package])) {
                throw new \LogicException(\sprintf('The package "%s" was not downloaded.', $package));
            }
            $this->remote_package_storage->save($entry, $contents[$package]['content']);
            foreach ($contents[$package]['extraFiles'] as $extra_filename => $extra_file_contents) {
                $this->remote_package_storage->save_extra_file($entry, $extra_filename, $extra_file_contents);
            }
            $new_installed[$package] = ['version' => $entry->version, 'dependencies' => $contents[$package]['dependencies'] ?? [], 'extraFiles' => array_keys($contents[$package]['extraFiles'])];
            $downloaded_packages[] = $package;
            unset($contents[$package]);
        }
        if ($contents) {
            throw new \LogicException(\sprintf('The following packages were unexpectedly downloaded: "%s".', implode('", "', array_keys($contents))));
        }
        $this->save_installed($new_installed);
        return $downloaded_packages;
    }
    /**
     * @return string[]
     */
    public function get_dependencies(string $import_name): array
    {
        $installed = $this->load_installed();
        if (!isset($installed[$import_name])) {
            throw new \InvalidArgumentException(\sprintf('The "%s" vendor asset is missing. Run "php bin/console importmap:install".', $import_name));
        }
        return $installed[$import_name]['dependencies'];
    }
    public function get_vendor_dir(): string
    {
        return $this->remote_package_storage->get_storage_dir();
    }
    /**
     * @return array<string, array{path: string, version: string, dependencies: array<string, string>, extraFiles: array<string, string>}>
     */
    private function load_installed(): array
    {
        if (isset($this->installed)) {
            return $this->installed;
        }
        $installed_path = $this->remote_package_storage->get_storage_dir() . '/installed.php';
        $installed = is_file($installed_path) ? (static fn() => include $installed_path)() : [];
        foreach ($installed as $package => $data) {
            if (!isset($data['version'])) {
                throw new \InvalidArgumentException(\sprintf('The package "%s" is missing its version.', $package));
            }
            if (!isset($data['dependencies'])) {
                throw new \LogicException(\sprintf('The package "%s" is missing its dependencies.', $package));
            }
            if (!isset($data['extraFiles'])) {
                $installed[$package]['extraFiles'] = [];
            }
        }
        return $this->installed = $installed;
    }
    private function save_installed(array $installed): void
    {
        $this->installed = $installed;
        $this->filesystem->dump_file($this->remote_package_storage->get_storage_dir() . '/installed.php', '<?php return ' . var_export($installed, true) . ';');
    }
    private function are_all_extra_files_downloaded(Import_Map_Entry $entry, array $extra_filenames): bool
    {
        foreach ($extra_filenames as $extra_filename) {
            if (!$this->remote_package_storage->is_extra_file_downloaded($entry, $extra_filename)) {
                return false;
            }
        }
        return true;
    }
}