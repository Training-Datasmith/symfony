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
namespace Symfony\Component\Asset_Mapper\Path;

/**
 * Writes asset files to their public location.
 */
interface Public_Assets_Filesystem_Interface
{
    /**
     * Write the contents of a file to the public location.
     */
    public function write(string $path, string $contents): void;
    /**
     * Copy a local file to the public location.
     */
    public function copy(string $origin_path, string $path): void;
    /**
     * A string representation of the public directory, used for feedback.
     */
    public function get_destination_path(): string;
}