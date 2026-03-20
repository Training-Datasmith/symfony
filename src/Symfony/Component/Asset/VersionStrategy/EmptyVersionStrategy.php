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
 * Disable version for all assets.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class Empty_Version_Strategy implements Version_Strategy_Interface
{
    public function get_version(string $path): string
    {
        return '';
    }
    public function apply_version(string $path): string
    {
        return $path;
    }
}