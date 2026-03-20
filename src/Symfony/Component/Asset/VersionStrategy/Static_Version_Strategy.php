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
namespace Symfony\Component\Asset\Version_Strategy;

/**
 * Returns the same version for all assets.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class Static_Version_Strategy implements Version_Strategy_Interface
{
    private readonly string $format;
    /**
     * @param string $version Version number
     * @param string $format  Url format
     */
    public function __construct(private readonly string $version, ?string $format = null)
    {
        $this->format = $format ?: '%s?%s';
    }
    public function get_version(string $path): string
    {
        return $this->version;
    }
    public function apply_version(string $path): string
    {
        $versionized = \sprintf($this->format, ltrim($path, '/'), $this->get_version($path));
        if ($path && '/' === $path[0]) {
            return '/' . $versionized;
        }
        return $versionized;
    }
}