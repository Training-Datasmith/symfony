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

/**
 * Represents a package that should be installed or updated.
 *
 * @author Kévin Dunglas <kevin@dunglas.dev>
 */
final readonly class Package_Require_Options
{
    public string $import_name;
    public function __construct(
        /**
         * The "package-name/path" of the remote package.
         */
        public string $package_module_specifier,
        public ?string $version_constraint = null,
        ?string $import_name = null,
        public ?string $path = null,
        public bool $entrypoint = false
    )
    {
        $this->import_name = $import_name ?: $package_module_specifier;
    }
}