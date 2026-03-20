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
namespace Symfony\Component\Asset_Mapper\Import_Map\Resolver;

use Symfony\Component\Asset_Mapper\Import_Map\Import_Map_Entry;
use Symfony\Component\Asset_Mapper\Import_Map\Package_Require_Options;
interface Package_Resolver_Interface
{
    /**
     * Grabs the URLs for the given packages and converts them to ImportMapEntry objects.
     *
     * If "download" is specified in PackageRequireOptions, the resolved package
     * contents should be included.
     *
     * @param PackageRequireOptions[] $packagesToRequire
     *
     * @return ResolvedImportMapPackage[] The import map entries that should be added
     */
    public function resolve_packages(array $packages_to_require): array;
    /**
     * Downloads the contents of the given packages.
     *
     * The returned array should be a map using the same keys as $importMapEntries.
     *
     * The dependencies are an array of module names that are imported by the package.
     *
     * @param array<string, ImportMapEntry> $importMapEntries
     *
     * @return array<string, array{content: string, dependencies: string[], extraFiles: array<string, string>}>
     */
    public function download_packages(array $import_map_entries, ?callable $progress_callback = null): array;
}