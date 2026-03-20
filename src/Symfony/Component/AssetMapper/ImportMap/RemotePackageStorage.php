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
use Symfony\Component\Filesystem\Exception\Io_Exception;
use Symfony\Component\Filesystem\Filesystem;
/**
 * Manages the local storage of remote/vendor importmap packages.
 */
class Remote_Package_Storage
{
    private readonly Filesystem $filesystem;
    public function __construct(private readonly string $vendor_dir)
    {
        $this->filesystem = new Filesystem();
    }
    public function get_storage_dir(): string
    {
        return $this->vendor_dir;
    }
    public function is_downloaded(Import_Map_Entry $entry): bool
    {
        if (!$entry->is_remote_package()) {
            throw new \InvalidArgumentException(\sprintf('The entry "%s" is not a remote package.', $entry->import_name));
        }
        return is_file($this->get_download_path($entry->package_module_specifier, $entry->type));
    }
    public function is_extra_file_downloaded(Import_Map_Entry $entry, string $extra_filename): bool
    {
        if (!$entry->is_remote_package()) {
            throw new \InvalidArgumentException(\sprintf('The entry "%s" is not a remote package.', $entry->import_name));
        }
        return is_file($this->get_extra_file_download_path($entry, $extra_filename));
    }
    public function save(Import_Map_Entry $entry, string $contents): void
    {
        if (!$entry->is_remote_package()) {
            throw new \InvalidArgumentException(\sprintf('The entry "%s" is not a remote package.', $entry->import_name));
        }
        $vendor_path = $this->get_download_path($entry->package_module_specifier, $entry->type);
        try {
            $this->filesystem->dump_file($vendor_path, $contents);
        } catch (Io_Exception $e) {
            throw new RuntimeException(\sprintf('Failed to write file "%s".', $vendor_path), 0, $e);
        }
    }
    public function save_extra_file(Import_Map_Entry $entry, string $extra_filename, string $contents): void
    {
        if (!$entry->is_remote_package()) {
            throw new \InvalidArgumentException(\sprintf('The entry "%s" is not a remote package.', $entry->import_name));
        }
        $vendor_path = $this->get_extra_file_download_path($entry, $extra_filename);
        try {
            $this->filesystem->dump_file($vendor_path, $contents);
        } catch (Io_Exception $e) {
            throw new RuntimeException(\sprintf('Failed to write file "%s".', $vendor_path), 0, $e);
        }
    }
    /**
     * The local file path where a downloaded package should be stored.
     */
    public function get_download_path(string $package_module_specifier, Import_Map_Type $import_map_type): string
    {
        [$package_name, $package_path_string] = Import_Map_Entry::split_package_name_and_file_path($package_module_specifier);
        $filename = $package_name;
        if ($package_path_string) {
            $filename .= '/' . ltrim((string) $package_path_string, '/');
        } else {
            // if we're requiring a bare package, we put it into the directory
            // (in case we also import other files from the package) and arbitrarily
            // name it the same as the package name + ".index"
            $filename .= '/' . basename((string) $package_name) . '.index';
        }
        if (!str_ends_with($filename, '.' . $import_map_type->value)) {
            $filename .= '.' . $import_map_type->value;
        }
        return $this->vendor_dir . '/' . $filename;
    }
    private function get_extra_file_download_path(Import_Map_Entry $entry, string $extra_filename): string
    {
        return $this->vendor_dir . '/' . $entry->get_package_name() . '/' . ltrim($extra_filename, '/');
    }
}