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

/**
 * Finds and returns assets in the pipeline.
 *
 * @author Ryan Weaver <ryan@symfonycasts.com>
 */
interface Asset_Mapper_Interface
{
    /**
     * Given the logical path (e.g. path relative to a mapped directory), return the asset.
     */
    public function get_asset(string $logical_path): ?Mapped_Asset;
    /**
     * Returns all mapped assets.
     *
     * @return iterable<MappedAsset>
     */
    public function all_assets(): iterable;
    /**
     * Fetches the asset given its source path (i.e. filesystem path).
     */
    public function get_asset_from_source_path(string $source_path): ?Mapped_Asset;
    /**
     * Returns the public path for this asset, if it can be found.
     */
    public function get_public_path(string $logical_path): ?string;
}