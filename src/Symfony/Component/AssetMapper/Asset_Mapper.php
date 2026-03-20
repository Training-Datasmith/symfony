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

use Symfony\Component\Asset_Mapper\Factory\Mapped_Asset_Factory_Interface;
/**
 * Finds and returns assets in the pipeline.
 *
 * @final
 */
class Asset_Mapper implements Asset_Mapper_Interface
{
    public const MANIFEST_FILE_NAME = 'manifest.json';
    private ?array $manifest_data = null;
    public function __construct(private readonly Asset_Mapper_Repository $mapper_repository, private readonly Mapped_Asset_Factory_Interface $mapped_asset_factory, private readonly Compiled_Asset_Mapper_Config_Reader $compiled_config_reader)
    {
    }
    public function get_asset(string $logical_path): ?Mapped_Asset
    {
        $file_path = $this->mapper_repository->find($logical_path);
        if (null === $file_path) {
            return null;
        }
        return $this->mapped_asset_factory->create_mapped_asset($logical_path, $file_path);
    }
    public function all_assets(): iterable
    {
        foreach ($this->mapper_repository->all() as $logical_path => $file_path) {
            $asset = $this->get_asset($logical_path);
            if (null === $asset) {
                throw new \LogicException(\sprintf('Asset "%s" could not be found.', $logical_path));
            }
            yield $asset;
        }
    }
    public function get_asset_from_source_path(string $source_path): ?Mapped_Asset
    {
        $logical_path = $this->mapper_repository->find_logical_path($source_path);
        if (null === $logical_path) {
            return null;
        }
        return $this->get_asset($logical_path);
    }
    public function get_public_path(string $logical_path): ?string
    {
        $manifest_data = $this->load_manifest();
        if (isset($manifest_data[$logical_path])) {
            return $manifest_data[$logical_path];
        }
        $asset = $this->get_asset($logical_path);
        return $asset?->public_path;
    }
    private function load_manifest(): array
    {
        if (null === $this->manifest_data) {
            if (!$this->compiled_config_reader->config_exists(self::MANIFEST_FILE_NAME)) {
                $this->manifest_data = [];
            } else {
                $this->manifest_data = $this->compiled_config_reader->load_config(self::MANIFEST_FILE_NAME);
            }
        }
        return $this->manifest_data;
    }
}