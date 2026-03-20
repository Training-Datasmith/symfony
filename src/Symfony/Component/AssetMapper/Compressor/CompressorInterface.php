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
namespace Symfony\Component\Asset_Mapper\Compressor;

/**
 * Compresses a file.
 *
 * @author Kévin Dunglas <kevin@dunglas.dev>
 */
interface Compressor_Interface
{
    // Loosely based on https://caddyserver.com/docs/caddyfile/directives/encode#match
    public const DEFAULT_EXTENSIONS = ['css', 'cur', 'eot', 'html', 'js', 'json', 'md', 'otc', 'otf', 'proto', 'rss', 'rtf', 'svg', 'ttc', 'ttf', 'txt', 'wasm', 'xml'];
    public function compress(string $path): void;
}