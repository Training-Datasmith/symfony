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

interface Public_Assets_Path_Resolver_Interface
{
    /**
     * The path that should be prefixed on all asset paths to point to the output location.
     */
    public function resolve_public_path(string $logical_path): string;
}