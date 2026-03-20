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
namespace Symfony\Component\Asset_Mapper\Factory;

use Symfony\Component\Asset_Mapper\Mapped_Asset;
use Symfony\Component\Config\Config_Cache;
use Symfony\Component\Config\Resource\Directory_Resource;
use Symfony\Component\Config\Resource\File_Existence_Resource;
use Symfony\Component\Config\Resource\File_Resource;
use Symfony\Component\Config\Resource\Resource_Interface;
use Symfony\Component\Filesystem\Filesystem;
/**
 * Decorates the asset factory to load MappedAssets from cache when possible.
 */
class Cached_Mapped_Asset_Factory implements Mapped_Asset_Factory_Interface
{
    public function __construct(private readonly Mapped_Asset_Factory_Interface $inner_factory, private readonly string $cache_dir, private readonly bool $debug)
    {
    }
    public function create_mapped_asset(string $logical_path, string $source_path): ?Mapped_Asset
    {
        $cache_path = $this->get_cache_file_path($logical_path, $source_path);
        $config_cache = new Config_Cache($cache_path, $this->debug);
        if ($config_cache->is_fresh()) {
            return unserialize((new Filesystem())->read_file($cache_path), ['allowed_classes' => true]);
        }
        $mapped_asset = $this->inner_factory->create_mapped_asset($logical_path, $source_path);
        if (!$mapped_asset) {
            return null;
        }
        $resources = $this->collect_resources_from_asset($mapped_asset);
        $config_cache->write(serialize($mapped_asset), $resources);
        return $mapped_asset;
    }
    private function get_cache_file_path(string $logical_path, string $source_path): string
    {
        return $this->cache_dir . '/' . hash('xxh128', $logical_path . ':' . $source_path) . '.php';
    }
    /**
     * @return ResourceInterface[]
     */
    private function collect_resources_from_asset(Mapped_Asset $mapped_asset): array
    {
        $resources = array_map(static fn(string $path): \Symfony\Component\Config\Resource\Directory_Resource|\Symfony\Component\Config\Resource\File_Resource => is_dir($path) ? new Directory_Resource($path) : new File_Resource($path), $mapped_asset->get_file_dependencies());
        $resources[] = new File_Resource($mapped_asset->source_path);
        foreach ($mapped_asset->get_dependencies() as $asset_dependency) {
            $resources = array_merge($resources, $this->collect_resources_from_asset($asset_dependency));
        }
        foreach ($mapped_asset->get_java_script_imports() as $import) {
            $resources[] = new File_Existence_Resource($import->asset_source_path);
        }
        return $resources;
    }
}