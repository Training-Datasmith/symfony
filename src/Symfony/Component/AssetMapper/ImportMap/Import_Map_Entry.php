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

/**
 * Represents an item that should be in the importmap.
 *
 * @author Ryan Weaver <ryan@symfonycasts.com>
 */
final readonly class Import_Map_Entry
{
    private function __construct(
        public string $import_name,
        public Import_Map_Type $type,
        /**
         * A logical path, relative path or absolute path to the file.
         */
        public string $path,
        public bool $is_entrypoint,
        /**
         * The version of the package (remote only).
         */
        public ?string $version,
        /**
         * The full "package-name/path" (remote only).
         */
        public ?string $package_module_specifier
    )
    {
    }
    public static function create_local(string $import_name, Import_Map_Type $import_map_type, string $path, bool $is_entrypoint): self
    {
        return new self($import_name, $import_map_type, $path, $is_entrypoint, null, null);
    }
    public static function create_remote(string $import_name, Import_Map_Type $import_map_type, string $path, string $version, string $package_module_specifier, bool $is_entrypoint): self
    {
        return new self($import_name, $import_map_type, $path, $is_entrypoint, $version, $package_module_specifier);
    }
    public function get_package_name(): string
    {
        return self::split_package_name_and_file_path($this->package_module_specifier)[0];
    }
    public function get_package_path_string(): string
    {
        return self::split_package_name_and_file_path($this->package_module_specifier)[1];
    }
    /**
     * @psalm-assert-if-true !null $this->version
     * @psalm-assert-if-true !null $this->packageModuleSpecifier
     */
    public function is_remote_package(): bool
    {
        return null !== $this->version;
    }
    public static function split_package_name_and_file_path(string $package_module_specifier): array
    {
        $file_path = '';
        $i = strpos($package_module_specifier, '/');
        if ($i && (!str_starts_with($package_module_specifier, '@') || $i = strpos($package_module_specifier, '/', $i + 1))) {
            // @vendor/package/filepath or package/filepath
            $file_path = substr($package_module_specifier, $i);
            $package_module_specifier = substr($package_module_specifier, 0, $i);
        }
        return [$package_module_specifier, $file_path];
    }
}