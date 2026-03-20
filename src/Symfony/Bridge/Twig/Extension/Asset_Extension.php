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
namespace Symfony\Bridge\Twig\Extension;

use Symfony\Component\Asset\Packages;
use Twig\Extension\Abstract_Extension;
use Twig\Twig_Function;
/**
 * Twig extension for the Symfony Asset component.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
final class Asset_Extension extends Abstract_Extension
{
    public function __construct(private readonly Packages $packages)
    {
    }
    public function get_functions(): array
    {
        return [new Twig_Function('asset', $this->get_asset_url(...)), new Twig_Function('asset_version', $this->get_asset_version(...))];
    }
    /**
     * Returns the public url/path of an asset.
     *
     * If the package used to generate the path is an instance of
     * UrlPackage, you will always get a URL and not a path.
     */
    public function get_asset_url(string $path, ?string $package_name = null): string
    {
        return $this->packages->get_url($path, $package_name);
    }
    /**
     * Returns the version of an asset.
     */
    public function get_asset_version(string $path, ?string $package_name = null): string
    {
        return $this->packages->get_version($path, $package_name);
    }
}