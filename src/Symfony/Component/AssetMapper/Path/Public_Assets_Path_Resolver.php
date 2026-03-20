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

class Public_Assets_Path_Resolver implements Public_Assets_Path_Resolver_Interface
{
    private readonly string $public_prefix;
    public function __construct(string $public_prefix = '/assets/')
    {
        // ensure that the public prefix always starts and ends with a single slash
        $this->public_prefix = '/' . trim($public_prefix, '/') . '/';
    }
    public function resolve_public_path(string $logical_path): string
    {
        return $this->public_prefix . ltrim($logical_path, '/');
    }
}