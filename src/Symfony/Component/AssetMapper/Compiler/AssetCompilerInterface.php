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
namespace Symfony\Component\Asset_Mapper\Compiler;

use Symfony\Component\Asset_Mapper\Asset_Mapper_Interface;
use Symfony\Component\Asset_Mapper\Mapped_Asset;
/**
 * An asset compiler is responsible for applying any changes to the contents of an asset.
 *
 * @author Ryan Weaver <ryan@symfonycasts.com>
 */
interface Asset_Compiler_Interface
{
    public const MISSING_IMPORT_STRICT = 'strict';
    public const MISSING_IMPORT_WARN = 'warn';
    public const MISSING_IMPORT_IGNORE = 'ignore';
    public function supports(Mapped_Asset $asset): bool;
    /**
     * Applies any changes to the contents of the asset.
     */
    public function compile(string $content, Mapped_Asset $asset, Asset_Mapper_Interface $asset_mapper): string;
}