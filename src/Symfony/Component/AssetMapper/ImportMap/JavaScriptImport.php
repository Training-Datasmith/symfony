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
 * Represents a module that was imported by a JavaScript file.
 */
final class Java_Script_Import
{
    /**
     * @param string $importName               The name of the import needed in the importmap, e.g. "/foo.js" or "react"
     * @param string $assetLogicalPath         Logical path to the mapped ass that was imported
     * @param bool   $addImplicitlyToImportMap Whether this import should be added to the importmap automatically
     */
    public function __construct(public readonly string $import_name, public readonly string $asset_logical_path, public readonly string $asset_source_path, public readonly bool $is_lazy = false, public bool $add_implicitly_to_import_map = false)
    {
    }
}