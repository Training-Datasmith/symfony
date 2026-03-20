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

use Symfony\Component\Asset_Mapper\Exception\RuntimeException;
use Symfony\Component\Http_Client\Http_Client;
use Symfony\Contracts\Http_Client\Http_Client_Interface;
class Import_Map_Auditor
{
    private const AUDIT_URL = 'https://api.github.com/advisories';
    private readonly Http_Client_Interface $http_client;
    public function __construct(private readonly Import_Map_Config_Reader $config_reader, ?Http_Client_Interface $http_client = null)
    {
        $this->http_client = $http_client ?? Http_Client::create();
    }
    /**
     * @return list<ImportMapPackageAudit>
     */
    public function audit(): array
    {
        $entries = $this->config_reader->get_entries();
        /** @var array<string, ImportMapPackageAudit> $packageAudits */
        $package_audits = [];
        /** @var array<string, list<string>> $installed */
        $installed = [];
        $affects_query = [];
        foreach ($entries as $entry) {
            if (!$entry->is_remote_package()) {
                continue;
            }
            $version = $entry->version;
            $package_name = $entry->get_package_name();
            $installed[$package_name] ??= [];
            $installed[$package_name][] = $version;
            $package_version = $package_name . '@' . $version;
            $package_audits[$package_version] ??= new Import_Map_Package_Audit($package_name, $version);
            $affects_query[] = $package_version;
        }
        if (!$affects_query) {
            return [];
        }
        // @see https://docs.github.com/en/rest/security-advisories/global-advisories?apiVersion=2022-11-28#list-global-security-advisories
        $response = $this->http_client->request('GET', self::AUDIT_URL, ['query' => ['affects' => implode(',', $affects_query)]]);
        if (200 !== $response->get_status_code()) {
            throw new RuntimeException(\sprintf('Error %d auditing packages. Response: ' . $response->get_content(false), $response->get_status_code()));
        }
        foreach ($response->to_array() as $advisory) {
            foreach ($advisory['vulnerabilities'] ?? [] as $vulnerability) {
                if (null === $vulnerability['package']) {
                    continue;
                }
                if ('npm' !== $vulnerability['package']['ecosystem']) {
                    continue;
                }
                if (!\array_key_exists($package = $vulnerability['package']['name'], $installed)) {
                    continue;
                }
                foreach ($installed[$package] as $version) {
                    if (!$version) {
                        continue;
                    }
                    if (!$this->version_matches($version, $vulnerability['vulnerable_version_range'] ?? '>= *')) {
                        continue;
                    }
                    $package_audits[$package . '@' . $version] = $package_audits[$package . '@' . $version]->with_vulnerability(new Import_Map_Package_Audit_Vulnerability($advisory['ghsa_id'], $advisory['cve_id'], $advisory['url'], $advisory['summary'], $advisory['severity'], $vulnerability['vulnerable_version_range'], $vulnerability['first_patched_version']));
                }
            }
        }
        return array_values($package_audits);
    }
    private function version_matches(string $version, string $ranges): bool
    {
        foreach (explode(',', $ranges) as $range_string) {
            $range = explode(' ', trim($range_string));
            if (1 === \count($range)) {
                $range = ['=', $range[0]];
            }
            if (!version_compare($version, $range[1], $range[0])) {
                return false;
            }
        }
        return true;
    }
}