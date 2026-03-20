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

use Symfony\Component\Asset_Mapper\Asset_Mapper_Compiler;
use Symfony\Component\Asset_Mapper\Exception\Circular_Assets_Exception;
use Symfony\Component\Asset_Mapper\Exception\RuntimeException;
use Symfony\Component\Asset_Mapper\Mapped_Asset;
use Symfony\Component\Asset_Mapper\Path\Public_Assets_Path_Resolver_Interface;
use Symfony\Component\Filesystem\Filesystem;
/**
 * Creates MappedAsset objects by reading their contents & passing it through compilers.
 */
class Mapped_Asset_Factory implements Mapped_Asset_Factory_Interface
{
    private const PREDIGESTED_REGEX = '/-([0-9a-zA-Z]{7,128}\.digested)/';
    private const PUBLIC_DIGEST_LENGTH = 7;
    private array $assets_cache = [];
    private array $assets_being_created = [];
    public function __construct(private readonly Public_Assets_Path_Resolver_Interface $assets_path_resolver, private readonly Asset_Mapper_Compiler $compiler, private readonly string $vendor_dir)
    {
    }
    public function create_mapped_asset(string $logical_path, string $source_path): ?Mapped_Asset
    {
        if (isset($this->assets_being_created[$logical_path])) {
            throw new Circular_Assets_Exception($this->assets_cache[$logical_path], \sprintf('Circular reference detected while creating asset for "%s": "%s".', $logical_path, implode(' -> ', $this->assets_being_created) . ' -> ' . $logical_path));
        }
        $this->assets_being_created[$logical_path] = $logical_path;
        if (!isset($this->assets_cache[$logical_path])) {
            $is_vendor = $this->is_vendor($source_path);
            $asset = new Mapped_Asset($logical_path, $source_path, $this->assets_path_resolver->resolve_public_path($logical_path), isVendor: $is_vendor);
            $this->assets_cache[$logical_path] = $asset;
            $content = $this->compile_content($asset);
            [$digest, $is_predigested] = $this->get_digest($asset, $content);
            $asset = new Mapped_Asset($asset->logical_path, $asset->source_path, $asset->public_path_without_digest, $this->get_public_path($asset, $content), $content, $digest, $is_predigested, $is_vendor, $asset->get_dependencies(), $asset->get_file_dependencies(), $asset->get_java_script_imports());
            $this->assets_cache[$logical_path] = $asset;
        }
        unset($this->assets_being_created[$logical_path]);
        return $this->assets_cache[$logical_path];
    }
    /**
     * Returns an array of "string digest" and "bool predigested".
     *
     * @return array{0: string, 1: bool}
     */
    private function get_digest(Mapped_Asset $asset, ?string $content): array
    {
        // check for a pre-digested file
        if (preg_match(self::PREDIGESTED_REGEX, $asset->logical_path, $matches)) {
            return [$matches[1], true];
        }
        // Use the compiled content if any
        if (null !== $content) {
            return [hash('xxh128', $content), false];
        }
        return [hash_file('xxh128', $asset->source_path), false];
    }
    private function compile_content(Mapped_Asset $asset): ?string
    {
        if (!is_file($asset->source_path)) {
            throw new RuntimeException(\sprintf('Asset source path "%s" could not be found.', $asset->source_path));
        }
        if (!$this->compiler->supports($asset)) {
            return null;
        }
        $content = (new Filesystem())->read_file($asset->source_path);
        $compiled = $this->compiler->compile($content, $asset);
        return $compiled !== $content ? $compiled : null;
    }
    private function get_public_path(Mapped_Asset $asset, ?string $content): string
    {
        [$digest, $is_predigested] = $this->get_digest($asset, $content);
        if ($is_predigested) {
            return $this->assets_path_resolver->resolve_public_path($asset->logical_path);
        }
        $digest = base64_encode(hex2bin($digest));
        $digest = substr($digest, 0, self::PUBLIC_DIGEST_LENGTH);
        $digest = strtr($digest, '+/', '-_');
        $digested_path = preg_replace('/\.(\w+)$/', "-{$digest}\\0", $asset->logical_path);
        return $this->assets_path_resolver->resolve_public_path($digested_path);
    }
    private function is_vendor(string $source_path): bool
    {
        $source_path = realpath($source_path);
        $vendor_dir = realpath($this->vendor_dir);
        return $source_path && $vendor_dir && str_starts_with($source_path, $vendor_dir);
    }
}