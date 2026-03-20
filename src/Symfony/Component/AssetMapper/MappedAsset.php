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
namespace Symfony\Component\Asset_Mapper;

use Symfony\Component\Asset_Mapper\Import_Map\Java_Script_Import;
/**
 * Represents a single asset in the asset mapper system.
 *
 * @author Ryan Weaver <ryan@symfonycasts.com>
 */
final class Mapped_Asset
{
    public readonly string $source_path;
    public readonly string $public_path;
    public readonly string $public_path_without_digest;
    public readonly string $public_extension;
    public readonly string $digest;
    public readonly bool $is_predigested;
    /**
     * @param MappedAsset[]      $dependencies      assets that the content of this asset depends on
     * @param string[]           $fileDependencies  files that the content of this asset depends on
     * @param JavaScriptImport[] $javaScriptImports
     */
    public function __construct(
        public readonly string $logical_path,
        ?string $source_path = null,
        ?string $public_path_without_digest = null,
        ?string $public_path = null,
        /**
         * The final content of this asset if different from the sourcePath.
         *
         * If null, the content should be read from the sourcePath.
         */
        public readonly ?string $content = null,
        ?string $digest = null,
        ?bool $is_predigested = null,
        public readonly bool $is_vendor = false,
        private array $dependencies = [],
        private array $file_dependencies = [],
        private array $java_script_imports = []
    )
    {
        if (null !== $source_path) {
            $this->source_path = $source_path;
        }
        if (null !== $public_path) {
            $this->public_path = $public_path;
        }
        if (null !== $public_path_without_digest) {
            $this->public_path_without_digest = $public_path_without_digest;
            $this->public_extension = pathinfo($public_path_without_digest, \PATHINFO_EXTENSION);
        }
        if (null !== $digest) {
            $this->digest = $digest;
        }
        if (null !== $is_predigested) {
            $this->is_predigested = $is_predigested;
        }
    }
    /**
     * Assets that the content of this asset depends on - for internal caching.
     *
     * @return MappedAsset[]
     */
    public function get_dependencies(): array
    {
        return $this->dependencies;
    }
    public function add_dependency(self $asset): void
    {
        $this->dependencies[] = $asset;
    }
    /**
     * @return string[]
     */
    public function get_file_dependencies(): array
    {
        return $this->file_dependencies;
    }
    public function add_file_dependency(string $source_path): void
    {
        $this->file_dependencies[] = $source_path;
    }
    /**
     * @return JavaScriptImport[]
     */
    public function get_java_script_imports(): array
    {
        return $this->java_script_imports;
    }
    public function add_java_script_import(Java_Script_Import $import): void
    {
        $this->java_script_imports[] = $import;
    }
}