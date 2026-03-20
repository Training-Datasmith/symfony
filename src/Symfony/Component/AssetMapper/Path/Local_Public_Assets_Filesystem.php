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

use Symfony\Component\Asset_Mapper\Compressor\Compressor_Interface;
use Symfony\Component\Filesystem\Filesystem;
class Local_Public_Assets_Filesystem implements Public_Assets_Filesystem_Interface
{
    private readonly Filesystem $filesystem;
    /**
     * @param string[] $extensionsToCompress
     */
    public function __construct(private readonly string $public_dir, private readonly ?Compressor_Interface $compressor = null, private readonly array $extensions_to_compress = [])
    {
        $this->filesystem = new Filesystem();
    }
    public function write(string $path, string $contents): void
    {
        $target_path = $this->public_dir . '/' . ltrim($path, '/');
        $this->filesystem->dump_file($target_path, $contents);
        $this->compress($target_path);
    }
    public function copy(string $origin_path, string $path): void
    {
        $target_path = $this->public_dir . '/' . ltrim($path, '/');
        $this->filesystem->copy($origin_path, $target_path, true);
        $this->compress($target_path);
    }
    public function get_destination_path(): string
    {
        return $this->public_dir;
    }
    private function compress(string $target_path): void
    {
        foreach ($this->extensions_to_compress as $ext) {
            if (!str_ends_with($target_path, ".{$ext}")) {
                continue;
            }
            $this->compressor?->compress($target_path);
            return;
        }
    }
}