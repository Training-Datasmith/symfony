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
namespace Symfony\Component\Asset_Mapper\Compiler;

use Symfony\Component\Asset_Mapper\Asset_Mapper_Interface;
use Symfony\Component\Asset_Mapper\Mapped_Asset;
use Symfony\Component\Filesystem\Path;
/**
 * Rewrites already-existing source map URLs to their final digested path.
 *
 * Originally sourced from https://github.com/rails/propshaft/blob/main/lib/propshaft/compiler/source_mapping_urls.rb
 */
final class Source_Mapping_Urls_Compiler implements Asset_Compiler_Interface
{
    private const SOURCE_MAPPING_PATTERN = '{^(//|/\*)# sourceMappingURL=(.+\.map)}m';
    public function supports(Mapped_Asset $asset): bool
    {
        return \in_array($asset->public_extension, ['css', 'js'], true);
    }
    public function compile(string $content, Mapped_Asset $asset, Asset_Mapper_Interface $asset_mapper): string
    {
        return preg_replace_callback(self::SOURCE_MAPPING_PATTERN, static function ($matches) use ($asset, $asset_mapper): string {
            $resolved_path = Path::join(\dirname($asset->source_path), $matches[2]);
            $dependent_asset = $asset_mapper->get_asset_from_source_path($resolved_path);
            if (!$dependent_asset) {
                // return original, unchanged path
                return $matches[0];
            }
            $asset->add_dependency($dependent_asset);
            $relative_path = Path::make_relative($dependent_asset->public_path, \dirname($asset->public_path_without_digest));
            return $matches[1] . '# sourceMappingURL=' . $relative_path;
        }, $content);
    }
}