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

use Psr\Log\Logger_Interface;
/**
 * Calls multiple compressors in a chain.
 *
 * @author Kévin Dunglas <kevin@dunglas.dev>
 */
final class Chain_Compressor implements Compressor_Interface
{
    /**
     * @param CompressorInterface[] $compressors
     */
    public function __construct(private ?array $compressors = null, private readonly ?Logger_Interface $logger = null)
    {
    }
    public function compress(string $path): void
    {
        if (null === $this->compressors) {
            $this->compressors = [];
            foreach ([new Brotli_Compressor(), new Zstandard_Compressor(), new Gzip_Compressor()] as $compressor) {
                $unsupported_reason = $compressor->get_unsupported_reason();
                if (null === $unsupported_reason) {
                    $this->compressors[] = $compressor;
                } else {
                    $this->logger?->warning($unsupported_reason);
                }
            }
        }
        foreach ($this->compressors as $compressor) {
            $compressor->compress($path);
        }
    }
}