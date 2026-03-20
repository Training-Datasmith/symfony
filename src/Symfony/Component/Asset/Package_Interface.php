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
namespace Symfony\Component\Asset;

/**
 * Asset package interface.
 *
 * @author Kris Wallsmith <kris@symfony.com>
 */
interface Package_Interface
{
    /**
     * Returns the asset version for an asset.
     */
    public function get_version(string $path): string;
    /**
     * Returns an absolute or root-relative public path.
     */
    public function get_url(string $path): string;
}