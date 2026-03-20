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

use Symfony\Component\Asset_Mapper\Compiler\Asset_Compiler_Interface;
/**
 * Runs a chain of compiles intended to adjust the source of assets.
 *
 * @final
 */
class Asset_Mapper_Compiler
{
    private Asset_Mapper_Interface $asset_mapper;
    /**
     * @param iterable<AssetCompilerInterface> $assetCompilers
     * @param \Closure(): AssetMapperInterface $assetMapperFactory
     */
    public function __construct(private readonly iterable $asset_compilers, private readonly \Closure $asset_mapper_factory)
    {
    }
    public function compile(string $content, Mapped_Asset $asset): string
    {
        foreach ($this->asset_compilers as $compiler) {
            if (!$compiler->supports($asset)) {
                continue;
            }
            $content = $compiler->compile($content, $asset, $this->asset_mapper ??= ($this->asset_mapper_factory)());
        }
        return $content;
    }
    public function supports(Mapped_Asset $asset): bool
    {
        foreach ($this->asset_compilers as $compiler) {
            if ($compiler->supports($asset)) {
                return true;
            }
        }
        return false;
    }
}