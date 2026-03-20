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

use Symfony\Component\Http_Client\Http_Client;
use Symfony\Contracts\Http_Client\Http_Client_Interface;
class Import_Map_Update_Checker
{
    private const URL_PACKAGE_METADATA = 'https://registry.npmjs.org/%s';
    private readonly Http_Client_Interface $http_client;
    public function __construct(private readonly Import_Map_Config_Reader $import_map_config_reader, ?Http_Client_Interface $http_client = null)
    {
        $this->http_client = new Batch_Http_Client($http_client ?? Http_Client::create());
    }
    /**
     * @param string[] $packages
     *
     * @return PackageUpdateInfo[]
     */
    public function get_available_updates(array $packages = []): array
    {
        $entries = $this->import_map_config_reader->get_entries();
        $update_infos = [];
        $responses = [];
        foreach ($entries as $entry) {
            if (!$entry->is_remote_package()) {
                continue;
            }
            if ($packages && !\in_array($entry->get_package_name(), $packages, true) && !\in_array($entry->import_name, $packages, true)) {
                continue;
            }
            $responses[$entry->import_name] = $this->http_client->request('GET', \sprintf(self::URL_PACKAGE_METADATA, $entry->get_package_name()), ['headers' => ['Accept' => 'application/vnd.npm.install-v1+json']]);
        }
        foreach ($responses as $import_name => $response) {
            $entry = $entries->get($import_name);
            if (200 !== $response->get_status_code()) {
                throw new \RuntimeException(\sprintf('Unable to get latest version for package "%s".', $entry->get_package_name()));
            }
            $update_info = new Package_Update_Info($entry->get_package_name(), $entry->version);
            try {
                $update_info->latest_version = json_decode($response->get_content(), true)['dist-tags']['latest'];
                $update_info->update_type = $this->get_update_type($update_info->current_version, $update_info->latest_version);
            } catch (\Exception $e) {
                throw new \RuntimeException(\sprintf('Unable to get latest version for package "%s".', $entry->get_package_name()), 0, $e);
            }
            $update_infos[$import_name] = $update_info;
        }
        return $update_infos;
    }
    private function get_version_part(string $version, int $part): string
    {
        return explode('.', $version)[$part] ?? $version;
    }
    private function get_update_type(string $current_version, string $latest_version): string
    {
        if (version_compare($current_version, $latest_version, '>')) {
            return Package_Update_Info::UPDATE_TYPE_DOWNGRADE;
        }
        if (version_compare($current_version, $latest_version, '==')) {
            return Package_Update_Info::UPDATE_TYPE_UP_TO_DATE;
        }
        if ($this->get_version_part($current_version, 0) < $this->get_version_part($latest_version, 0)) {
            return Package_Update_Info::UPDATE_TYPE_MAJOR;
        }
        if ($this->get_version_part($current_version, 1) < $this->get_version_part($latest_version, 1)) {
            return Package_Update_Info::UPDATE_TYPE_MINOR;
        }
        if ($this->get_version_part($current_version, 2) < $this->get_version_part($latest_version, 2)) {
            return Package_Update_Info::UPDATE_TYPE_PATCH;
        }
        throw new \LogicException(\sprintf('Unable to determine update type for "%s" and "%s".', $current_version, $latest_version));
    }
}