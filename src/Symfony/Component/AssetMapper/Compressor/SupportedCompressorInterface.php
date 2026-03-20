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
 * @internal
 *
 * @author Kévin Dunglas <kevin@dunglas.dev>
 */
interface Supported_Compressor_Interface extends Compressor_Interface
{
    /**
     * Returns null if the compressor is supported, or the reason why the compressor it is not.
     */
    public function get_unsupported_reason(): ?string;
}