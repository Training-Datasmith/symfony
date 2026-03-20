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

use Composer\Semver\Semver;
use Symfony\Component\Asset_Mapper\Exception\RuntimeException;
use Symfony\Component\Http_Client\Http_Client;
use Symfony\Contracts\Http_Client\Exception\Http_Exception_Interface;
use Symfony\Contracts\Http_Client\Http_Client_Interface;
class Import_Map_Version_Checker
{
    private const PACKAGE_METADATA_PATTERN = 'https://registry.npmjs.org/%package%/%version%';
    private readonly Http_Client_Interface $http_client;
    public function __construct(private readonly Import_Map_Config_Reader $import_map_config_reader, private readonly Remote_Package_Downloader $package_downloader, ?Http_Client_Interface $http_client = null)
    {
        $this->http_client = new Batch_Http_Client($http_client ?? Http_Client::create());
    }
    /**
     * @return PackageVersionProblem[]
     */
    public function check_versions(): array
    {
        $entries = $this->import_map_config_reader->get_entries();
        $packages = [];
        foreach ($entries as $entry) {
            if (!$entry->is_remote_package()) {
                continue;
            }
            $dependencies = $this->package_downloader->get_dependencies($entry->import_name);
            if (!$dependencies) {
                continue;
            }
            $package_name = $entry->get_package_name();
            $url = str_replace(['%package%', '%version%'], [$package_name, $entry->version], self::PACKAGE_METADATA_PATTERN);
            $packages[$package_name] = [$this->http_client->request('GET', $url), $dependencies];
        }
        $errors = [];
        $problems = [];
        foreach ($packages as $package_name => [$response, $dependencies]) {
            if (200 !== $response->get_status_code()) {
                $errors[] = [$package_name, $response];
                continue;
            }
            $data = json_decode($response->get_content(), true);
            // dependencies seem to be found in both places
            $package_dependencies = array_merge($data['dependencies'] ?? [], $data['peerDependencies'] ?? []);
            foreach ($dependencies as $dependency_name) {
                // dependency is not in the import map
                if (!$entries->has($dependency_name)) {
                    $dependency_version_constraint = $package_dependencies[$dependency_name] ?? 'unknown';
                    $problems[] = new Package_Version_Problem($package_name, $dependency_name, $dependency_version_constraint, null);
                    continue;
                }
                $dependency_package_name = $entries->get($dependency_name)->get_package_name();
                if (!isset($package_dependencies[$dependency_package_name])) {
                    continue;
                }
                $dependency_version_constraint = $package_dependencies[$dependency_package_name];
                if (!$this->is_version_satisfied($dependency_version_constraint, $entries->get($dependency_name)->version)) {
                    $problems[] = new Package_Version_Problem($package_name, $dependency_package_name, $dependency_version_constraint, $entries->get($dependency_name)->version);
                }
            }
        }
        try {
            ($errors[0][1] ?? null)?->get_headers();
        } catch (Http_Exception_Interface $e) {
            $response = $e->get_response();
            $package_names = implode('", "', array_column($errors, 0));
            throw new RuntimeException(\sprintf('Error %d finding metadata for package "%s". Response: ', $response->get_status_code(), $package_names) . $response->get_content(false), 0, $e);
        }
        return $problems;
    }
    /**
     * Converts npm-specific version constraints to composer-style.
     *
     * @internal
     */
    public static function convert_npm_constraint(string $version_constraint): ?string
    {
        // special npm constraint that don't translate to composer
        if (\in_array($version_constraint, ['latest', 'next'], true) || preg_match('/^(git|http|file):/', $version_constraint) || str_contains($version_constraint, '/')) {
            // GitHub shorthand like user/repo
            return null;
        }
        // remove whitespace around hyphens
        $version_constraint = preg_replace('/\s?-\s?/', '-', $version_constraint);
        $segments = explode(' ', (string) $version_constraint);
        $processed_segments = [];
        foreach ($segments as $segment) {
            if (str_contains($segment, '-') && !preg_match('/-(alpha|beta|rc)\./', $segment)) {
                // This is a range
                [$start, $end] = explode('-', $segment);
                $processed_segments[] = self::clean_version_segment(trim($start)) . ' - ' . self::clean_version_segment(trim($end));
            } elseif (preg_match('/^~(\d+\.\d+)$/', $segment, $matches)) {
                // Handle the tilde when only major.minor specified
                $base_version = $matches[1];
                $processed_segments[] = '>=' . $base_version . '.0';
                $processed_segments[] = '<' . $base_version[0] . '.' . ($base_version[2] + 1) . '.0';
            } else {
                $processed_segments[] = self::clean_version_segment($segment);
            }
        }
        return implode(' ', $processed_segments);
    }
    private static function clean_version_segment(string $segment): string
    {
        return str_replace(['v', '.x'], ['', '.*'], $segment);
    }
    private function is_version_satisfied(string $version_constraint, ?string $version): bool
    {
        if (!$version) {
            return false;
        }
        try {
            $version_constraint = self::convert_npm_constraint($version_constraint);
            // if version isn't parseable/convertible, assume it's not satisfied
            if (null === $version_constraint) {
                return false;
            }
            return Semver::satisfies($version, $version_constraint);
        } catch (\UnexpectedValueException) {
            return false;
        }
    }
}