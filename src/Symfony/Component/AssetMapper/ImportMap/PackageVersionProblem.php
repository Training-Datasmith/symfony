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
namespace Symfony\Component\Asset_Mapper\Import_Map;

final readonly class Package_Version_Problem
{
    public function __construct(public string $package_name, public string $dependency_package_name, public string $required_version_constraint, public ?string $installed_version)
    {
    }
}