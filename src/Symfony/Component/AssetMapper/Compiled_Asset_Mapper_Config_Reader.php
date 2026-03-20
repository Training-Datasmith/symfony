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
namespace Symfony\Component\Asset_Mapper;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
/**
 * Reads and writes compiled configuration files for asset mapper.
 */
class Compiled_Asset_Mapper_Config_Reader
{
    private readonly Filesystem $filesystem;
    public function __construct(private readonly string $directory)
    {
        $this->filesystem = new Filesystem();
    }
    public function config_exists(string $filename): bool
    {
        return is_file(Path::join($this->directory, $filename));
    }
    public function load_config(string $filename): array
    {
        return json_decode($this->filesystem->read_file(Path::join($this->directory, $filename)), true, 512, \JSON_THROW_ON_ERROR);
    }
    public function save_config(string $filename, array $data): string
    {
        $path = Path::join($this->directory, $filename);
        $this->filesystem->dump_file($path, json_encode($data, \JSON_PRETTY_PRINT | \JSON_THROW_ON_ERROR));
        return $path;
    }
    public function remove_config(string $filename): void
    {
        $path = Path::join($this->directory, $filename);
        if (is_file($path)) {
            $this->filesystem->remove($path);
        }
    }
}