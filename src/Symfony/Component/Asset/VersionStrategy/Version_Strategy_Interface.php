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
 * Asset version strategy interface.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
interface Version_Strategy_Interface
{
    /**
     * Returns the asset version for an asset.
     */
    public function get_version(string $path): string;
    /**
     * Applies version to the supplied path.
     */
    public function apply_version(string $path): string;
}