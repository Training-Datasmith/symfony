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

final readonly class Import_Map_Package_Audit
{
    public function __construct(
        public string $package,
        public ?string $version,
        /** @var array<ImportMapPackageAuditVulnerability> */
        public array $vulnerabilities = []
    )
    {
    }
    public function with_vulnerability(Import_Map_Package_Audit_Vulnerability $vulnerability): self
    {
        return new self($this->package, $this->version, [...$this->vulnerabilities, $vulnerability]);
    }
}