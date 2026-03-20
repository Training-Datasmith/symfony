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
namespace Symfony\Component\Asset_Mapper\Import_Map\Resolver;

use Symfony\Component\Asset_Mapper\Import_Map\Import_Map_Type;
use Symfony\Component\Asset_Mapper\Import_Map\Package_Require_Options;
final readonly class Resolved_Import_Map_Package
{
    public function __construct(public Package_Require_Options $require_options, public string $version, public Import_Map_Type $type)
    {
    }
}