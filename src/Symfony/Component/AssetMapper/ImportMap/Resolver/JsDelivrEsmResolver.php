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

use Symfony\Component\Asset_Mapper\Compiler\Css_Asset_Url_Compiler;
use Symfony\Component\Asset_Mapper\Exception\RuntimeException;
use Symfony\Component\Asset_Mapper\Import_Map\Batch_Http_Client;
use Symfony\Component\Asset_Mapper\Import_Map\Import_Map_Entry;
use Symfony\Component\Asset_Mapper\Import_Map\Import_Map_Type;
use Symfony\Component\Asset_Mapper\Import_Map\Package_Require_Options;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Http_Client\Http_Client;
use Symfony\Contracts\Http_Client\Exception\Http_Exception_Interface;
use Symfony\Contracts\Http_Client\Http_Client_Interface;
use Symfony\Contracts\Http_Client\Response_Interface;
final readonly class Js_Delivr_Esm_Resolver implements Package_Resolver_Interface
{
    public const URL_PATTERN_VERSION = 'https://data.jsdelivr.com/v1/packages/npm/%s/resolved';
    public const URL_PATTERN_DIST_CSS = 'https://cdn.jsdelivr.net/npm/%s@%s%s';
    public const URL_PATTERN_DIST = self::URL_PATTERN_DIST_CSS . '/+esm';
    public const URL_PATTERN_ENTRYPOINT = 'https://data.jsdelivr.com/v1/packages/npm/%s@%s/entrypoints';
    public const IMPORT_REGEX = '#(?:import\s*(?:[\w$]+,)?(?:(?:\{[^}]*\}|[\w$]+|\*\s*as\s+[\w$]+)\s*\bfrom\s*)?|export\s*(?:\{[^}]*\}|\*)\s*from\s*|await\simport\()("/npm/((?:@[^/]+/)?[^@]+?)(?:@([^/]+))?((?:/[^/]+)*?)/\+esm")(?:\)*)#';
    private const ES_MODULE_SHIMS = 'es-module-shims';
    private Http_Client_Interface $http_client;
    public function __construct(?Http_Client_Interface $http_client = null)
    {
        $this->http_client = new Batch_Http_Client($http_client ?? Http_Client::create());
    }
    public function resolve_packages(array $packages_to_require): array
    {
        $resolved_packages = [];
        resolve_packages:
        // request the version of each package
        $required_packages = [];
        foreach ($packages_to_require as $options) {
            $package_specifier = trim($options->package_module_specifier, '/');
            // avoid resolving the same package twice
            if (isset($resolved_packages[$package_specifier])) {
                continue;
            }
            [$package_name, $file_path] = Import_Map_Entry::split_package_name_and_file_path($package_specifier);
            $version_url = \sprintf(self::URL_PATTERN_VERSION, $package_name);
            if (null !== $options->version_constraint) {
                $version_url .= '?specifier=' . urlencode($options->version_constraint);
            }
            $response = $this->http_client->request('GET', $version_url);
            $required_packages[] = [
                $options,
                $response,
                $package_name,
                $file_path,
                /* resolved version */
                null,
            ];
        }
        // use the version of each package to request the contents
        $find_version_errors = [];
        $entrypoint_responses = [];
        foreach ($required_packages as $i => [$options, $response, $package_name, $file_path]) {
            if (200 !== $response->get_status_code()) {
                $find_version_errors[] = [$package_name, $response];
                continue;
            }
            $version = $response->to_array()['version'];
            if (null === $version) {
                throw new RuntimeException(\sprintf('Unable to find the latest version for package "%s" - try specifying the version manually.', $package_name));
            }
            $pattern = $this->resolve_url_pattern($package_name, $file_path);
            $required_packages[$i][1] = $this->http_client->request('GET', \sprintf($pattern, $package_name, $version, $file_path));
            $required_packages[$i][4] = $version;
            if (!$file_path) {
                $entrypoint_responses[$package_name] = [$this->http_client->request('GET', \sprintf(self::URL_PATTERN_ENTRYPOINT, $package_name, $version)), $version];
            }
        }
        try {
            ($find_version_errors[0][1] ?? null)?->get_headers();
        } catch (Http_Exception_Interface $e) {
            $response = $e->get_response();
            $packages = implode('", "', array_column($find_version_errors, 0));
            throw new RuntimeException(\sprintf('Error %d finding version from jsDelivr for the following packages: "%s". Check your package names. Response: ', $response->get_status_code(), $packages) . $response->get_content(false), 0, $e);
        }
        // process the contents of each package & add the resolved package
        $packages_to_require = [];
        $get_content_errors = [];
        foreach ($required_packages as [$options, $response, $package_name, $file_path, $version]) {
            if (200 !== $response->get_status_code()) {
                $get_content_errors[] = [$options->package_module_specifier, $response];
                continue;
            }
            $content_type = $response->get_headers()['content-type'][0] ?? '';
            $type = str_starts_with($content_type, 'text/css') ? Import_Map_Type::CSS : Import_Map_Type::JS;
            $resolved_packages[$options->package_module_specifier] = new Resolved_Import_Map_Package($options, $version, $type);
            $packages_to_require = array_merge($packages_to_require, $this->fetch_package_requirements_from_imports($response->get_content()));
        }
        try {
            ($get_content_errors[0][1] ?? null)?->get_headers();
        } catch (Http_Exception_Interface $e) {
            $response = $e->get_response();
            $packages = implode('", "', array_column($get_content_errors, 0));
            throw new RuntimeException(\sprintf('Error %d requiring packages from jsDelivr for "%s". Check your package names. Response: ', $response->get_status_code(), $packages) . $response->get_content(false), 0, $e);
        }
        // process any pending CSS entrypoints
        $entrypoint_errors = [];
        foreach ($entrypoint_responses as $package => [$css_entrypoint_response, $version]) {
            if (200 !== $css_entrypoint_response->get_status_code()) {
                $entrypoint_errors[] = [$package, $css_entrypoint_response];
                continue;
            }
            $entrypoints = $css_entrypoint_response->to_array()['entrypoints'] ?? [];
            $css_file = $entrypoints['css']['file'] ?? null;
            $guessed = $entrypoints['css']['guessed'] ?? true;
            if (!$css_file) {
                continue;
            }
            if ($guessed) {
                continue;
            }
            $packages_to_require[] = new Package_Require_Options($package . $css_file, $version);
        }
        try {
            ($entrypoint_errors[0][1] ?? null)?->get_headers();
        } catch (Http_Exception_Interface $e) {
            $response = $e->get_response();
            $packages = implode('", "', array_column($entrypoint_errors, 0));
            throw new RuntimeException(\sprintf('Error %d checking for a CSS entrypoint for "%s". Response: ', $response->get_status_code(), $packages) . $response->get_content(false), 0, $e);
        }
        if ($packages_to_require) {
            goto resolve_packages;
        }
        return array_values($resolved_packages);
    }
    /**
     * @param ImportMapEntry[] $importMapEntries
     *
     * @return array<string, array{content: string, dependencies: string[], extraFiles: array<string, string>}>
     */
    public function download_packages(array $import_map_entries, ?callable $progress_callback = null): array
    {
        /** @var array<string, array{0: ResponseInterface, 1: ImportMapEntry}> $responses */
        $responses = [];
        foreach ($import_map_entries as $package => $entry) {
            if (!$entry->is_remote_package()) {
                throw new \InvalidArgumentException(\sprintf('The entry "%s" is not a remote package.', $entry->import_name));
            }
            $pattern = $this->resolve_url_pattern($entry->get_package_name(), $entry->get_package_path_string(), $entry->type);
            $url = \sprintf($pattern, $entry->get_package_name(), $entry->version, $entry->get_package_path_string());
            $responses[$package] = [$this->http_client->request('GET', $url), $entry];
        }
        $errors = [];
        $contents = [];
        $extra_file_responses = [];
        foreach ($responses as $package => [$response, $entry]) {
            if (200 !== $response->get_status_code()) {
                $errors[] = [$package, $response];
                continue;
            }
            if ($progress_callback) {
                $progress_callback($package, 'started', $response, \count($responses));
            }
            $dependencies = [];
            $extra_files = [];
            $contents[$package] = ['content' => $this->make_imports_bare($response->get_content(), $dependencies, $extra_files, $entry->type, $entry->get_package_path_string()), 'dependencies' => $dependencies, 'extraFiles' => []];
            if (0 !== \count($extra_files)) {
                $extra_file_responses[$package] = [];
                foreach ($extra_files as $extra_file) {
                    $extra_file_responses[$package][] = [$this->http_client->request('GET', \sprintf(self::URL_PATTERN_DIST_CSS, $entry->get_package_name(), $entry->version, $extra_file)), $extra_file, $entry->get_package_name(), $entry->version];
                }
            }
            if ($progress_callback) {
                $progress_callback($package, 'finished', $response, \count($responses));
            }
        }
        try {
            ($errors[0][1] ?? null)?->get_headers();
        } catch (Http_Exception_Interface $e) {
            $response = $e->get_response();
            $packages = implode('", "', array_column($errors, 0));
            throw new RuntimeException(\sprintf('Error %d downloading packages from jsDelivr for "%s". Check your package names. Response: ', $response->get_status_code(), $packages) . $response->get_content(false), 0, $e);
        }
        $extra_file_errors = [];
        download_extra_files:
        $package_file_responses = $extra_file_responses;
        $extra_file_responses = [];
        foreach ($package_file_responses as $package => $responses) {
            foreach ($responses as [$response, $extra_file, $package_name, $version]) {
                if (200 !== $response->get_status_code()) {
                    $extra_file_errors[] = [$package, $response];
                    continue;
                }
                $extra_files = [];
                $content = $response->get_content();
                if (str_ends_with((string) $extra_file, '.css')) {
                    $content = $this->make_imports_bare($content, $dependencies, $extra_files, Import_Map_Type::CSS, $extra_file);
                }
                $contents[$package]['extraFiles'][$extra_file] = $content;
                if (0 !== \count($extra_files)) {
                    $extra_file_responses[$package] = [];
                    foreach ($extra_files as $new_extra_file) {
                        $extra_file_responses[$package][] = [$this->http_client->request('GET', \sprintf(self::URL_PATTERN_DIST_CSS, $package_name, $version, $new_extra_file)), $new_extra_file, $package_name, $version];
                    }
                }
            }
        }
        if ($extra_file_responses) {
            goto download_extra_files;
        }
        try {
            ($extra_file_errors[0][1] ?? null)?->get_headers();
        } catch (Http_Exception_Interface $e) {
            $response = $e->get_response();
            $packages = implode('", "', array_column($extra_file_errors, 0));
            throw new RuntimeException(\sprintf('Error %d downloading extra imported files from jsDelivr for "%s". Response: ', $response->get_status_code(), $packages) . $response->get_content(false), 0, $e);
        }
        return $contents;
    }
    /**
     * Parses the very specific import syntax used by jsDelivr.
     *
     * Replaces those with normal import "package/name" statements and
     * records the package as a dependency, so it can be downloaded and
     * added to the importmap.
     *
     * @return PackageRequireOptions[]
     */
    private function fetch_package_requirements_from_imports(string $content): array
    {
        // imports from jsdelivr follow a predictable format
        preg_match_all(self::IMPORT_REGEX, $content, $matches);
        $dependencies = [];
        foreach ($matches[2] as $index => $package_name) {
            $version = $matches[3][$index] ?: null;
            $package_name .= $matches[4][$index];
            // add the path if any
            $dependencies[] = new Package_Require_Options($package_name, $version);
        }
        return $dependencies;
    }
    /**
     * Parses the very specific import syntax used by jsDelivr.
     *
     * Replaces those with normal import "package/name" statements.
     */
    private function make_imports_bare(string $content, array &$dependencies, array &$extra_files, Import_Map_Type $type, string $source_file_path): string
    {
        if (Import_Map_Type::JS === $type) {
            $content = preg_replace_callback(self::IMPORT_REGEX, static function ($matches) use (&$dependencies): string {
                $package_name = $matches[2] . $matches[4];
                // add the path if any
                $dependencies[] = $package_name;
                // replace the "/npm/package@version/+esm" with "package@version"
                return str_replace($matches[1], \sprintf('"%s"', $package_name), $matches[0]);
            }, $content);
            // source maps are not also downloaded - so remove the sourceMappingURL
            // remove the final one only (in case sourceMappingURL is used in the code)
            if (false !== $last_pos = strrpos((string) $content, '//# sourceMappingURL=')) {
                return substr((string) $content, 0, $last_pos) . preg_replace('{//# sourceMappingURL=.*$}m', '', substr((string) $content, $last_pos));
            }
            return $content;
        }
        preg_match_all(Css_Asset_Url_Compiler::ASSET_URL_PATTERN, $content, $matches);
        foreach ($matches[2] as $path) {
            if (str_starts_with($path, 'data:')) {
                continue;
            }
            if (str_starts_with($path, 'http://')) {
                continue;
            }
            if (str_starts_with($path, 'https://')) {
                continue;
            }
            $extra_files[] = Path::join(\dirname($source_file_path), $path);
        }
        return preg_replace('{/\*# sourceMappingURL=[^ ]*+ \*/}', '', $content);
    }
    /**
     * Determine the URL pattern to be used by the HTTP Client.
     */
    private function resolve_url_pattern(string $package_name, string $path, ?Import_Map_Type $type = null): string
    {
        // The URL for the es-module-shims polyfill package uses the CSS pattern to
        // prevent a syntax error in the browser console, so check the package name
        // as part of the condition.
        if (self::ES_MODULE_SHIMS === $package_name || str_ends_with($path, '.css') || Import_Map_Type::CSS === $type) {
            return self::URL_PATTERN_DIST_CSS;
        }
        return self::URL_PATTERN_DIST;
    }
}