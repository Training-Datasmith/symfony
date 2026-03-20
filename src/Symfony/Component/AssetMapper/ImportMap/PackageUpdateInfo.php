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

class Package_Update_Info
{
    public const UPDATE_TYPE_DOWNGRADE = 'downgrade';
    public const UPDATE_TYPE_UP_TO_DATE = 'up-to-date';
    public const UPDATE_TYPE_MAJOR = 'major';
    public const UPDATE_TYPE_MINOR = 'minor';
    public const UPDATE_TYPE_PATCH = 'patch';
    public function __construct(public readonly string $package_name, public readonly string $current_version, public ?string $latest_version = null, public ?string $update_type = null)
    {
    }
    public function has_update(): bool
    {
        return !\in_array($this->update_type, [self::UPDATE_TYPE_DOWNGRADE, self::UPDATE_TYPE_DOWNGRADE, self::UPDATE_TYPE_UP_TO_DATE], true);
    }
}