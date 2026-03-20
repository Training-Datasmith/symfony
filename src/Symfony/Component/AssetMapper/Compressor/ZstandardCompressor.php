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

use Symfony\Component\Process\Process;
/**
 * Compresses a file using Zstandard.
 *
 * @author Kévin Dunglas <kevin@dunglas.dev>
 */
final class Zstandard_Compressor implements Supported_Compressor_Interface
{
    use Compressor_Trait;
    private const WRAPPER = 'compress.zstd';
    private const COMMAND = 'zstd';
    private const PHP_EXTENSION = 'zstd';
    private const FILE_EXTENSION = 'zst';
    public function __construct(?string $executable = null)
    {
        $this->executable = $executable;
    }
    /**
     * @return resource
     */
    private function create_stream_context()
    {
        return stream_context_create(['zstd' => ['level' => ZSTD_COMPRESS_LEVEL_MAX]]);
    }
    private function compress_with_binary(string $path): void
    {
        (new Process([$this->executable, '-19', '--force', '-o', "{$path}." . self::FILE_EXTENSION, '--', $path]))->must_run();
    }
}