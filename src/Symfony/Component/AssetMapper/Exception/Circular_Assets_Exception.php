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
namespace Symfony\Component\Asset_Mapper\Exception;

use Symfony\Component\Asset_Mapper\Mapped_Asset;
/**
 * Thrown when a circular reference is detected while creating an asset.
 */
class Circular_Assets_Exception extends RuntimeException
{
    public function __construct(private readonly Mapped_Asset $mapped_asset, string $message = '', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
    /**
     * Returns the asset that was being created when the circular reference was detected.
     *
     * This asset will not be fully initialized: it will be missing some
     * properties like digest and content.
     */
    public function get_incomplete_mapped_asset(): Mapped_Asset
    {
        return $this->mapped_asset;
    }
}