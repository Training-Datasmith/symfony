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

use Symfony\Component\Asset\Package_Interface;
/**
 * Decorates asset packages to support resolving assets from the asset mapper.
 *
 * @author Ryan Weaver <ryan@symfonycasts.com>
 */
final readonly class Mapper_Aware_Asset_Package implements Package_Interface
{
    public function __construct(private Package_Interface $inner_package, private Asset_Mapper_Interface $asset_mapper)
    {
    }
    public function get_version(string $path): string
    {
        return $this->inner_package->get_version($path);
    }
    public function get_url(string $path): string
    {
        $public_path = $this->asset_mapper->get_public_path($path);
        if ($public_path) {
            $path = ltrim($public_path, '/');
        }
        return $this->inner_package->get_url($path);
    }
}