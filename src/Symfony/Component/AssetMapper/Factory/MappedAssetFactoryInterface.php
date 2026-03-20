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
namespace Symfony\Component\Asset_Mapper\Factory;

use Symfony\Component\Asset_Mapper\Mapped_Asset;
interface Mapped_Asset_Factory_Interface
{
    public function create_mapped_asset(string $logical_path, string $source_path): ?Mapped_Asset;
}