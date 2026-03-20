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
namespace Symfony\Component\Asset\Version_Strategy;

use Symfony\Component\Asset\Exception\Asset_Not_Found_Exception;
use Symfony\Component\Asset\Exception\LogicException;
use Symfony\Component\Asset\Exception\RuntimeException;
use Symfony\Contracts\Http_Client\Exception\Client_Exception_Interface;
use Symfony\Contracts\Http_Client\Exception\Decoding_Exception_Interface;
use Symfony\Contracts\Http_Client\Http_Client_Interface;
/**
 * Reads the versioned path of an asset from a JSON manifest file.
 *
 * For example, the manifest file might look like this:
 *     {
 *         "main.js": "main.abc123.js",
 *         "css/styles.css": "css/styles.555abc.css"
 *     }
 *
 * You could then ask for the version of "main.js" or "css/styles.css".
 */
class Json_Manifest_Version_Strategy implements Version_Strategy_Interface
{
    private array $manifest_data;
    /**
     * @param string $manifestPath Absolute path to the manifest file
     * @param bool   $strictMode   Throws an exception for unknown paths
     */
    public function __construct(private readonly string $manifest_path, private readonly ?Http_Client_Interface $http_client = null, private readonly bool $strict_mode = false)
    {
        if (null === $this->http_client && ($scheme = parse_url($this->manifest_path, \PHP_URL_SCHEME)) && str_starts_with($scheme, 'http')) {
            throw new LogicException(\sprintf('The "%s" class needs an HTTP client to use a remote manifest. Try running "composer require symfony/http-client".', self::class));
        }
    }
    /**
     * With a manifest, we don't really know or care about what
     * the version is. Instead, this returns the path to the
     * versioned file.
     */
    public function get_version(string $path): string
    {
        return $this->apply_version($path);
    }
    public function apply_version(string $path): string
    {
        return $this->get_manifest_path($path) ?: $path;
    }
    private function get_manifest_path(string $path): ?string
    {
        if (!isset($this->manifest_data)) {
            if (null !== $this->http_client && ($scheme = parse_url($this->manifest_path, \PHP_URL_SCHEME)) && str_starts_with($scheme, 'http')) {
                try {
                    $this->manifest_data = $this->http_client->request('GET', $this->manifest_path, ['headers' => ['accept' => 'application/json']])->to_array();
                } catch (Decoding_Exception_Interface $e) {
                    throw new RuntimeException(\sprintf('Error parsing JSON from asset manifest URL "%s".', $this->manifest_path), 0, $e);
                } catch (Client_Exception_Interface $e) {
                    if ($this->strict_mode) {
                        throw new RuntimeException(\sprintf('Error loading JSON from asset manifest URL "%s".', $this->manifest_path), 0, $e);
                    }
                    $this->manifest_data = [];
                }
            } else if (!is_file($this->manifest_path)) {
                if ($this->strict_mode) {
                    throw new RuntimeException(\sprintf('Asset manifest file "%s" does not exist. Did you forget to build the assets with npm or yarn?', $this->manifest_path));
                }
                $this->manifest_data = [];
            } else {
                try {
                    $this->manifest_data = json_decode(file_get_contents($this->manifest_path), true, flags: \JSON_THROW_ON_ERROR);
                } catch (\Json_Exception $e) {
                    throw new RuntimeException(\sprintf('Error parsing JSON from asset manifest file "%s": ', $this->manifest_path) . $e->get_message(), previous: $e);
                }
            }
        }
        if (isset($this->manifest_data[$path])) {
            return $this->manifest_data[$path];
        }
        if ($this->strict_mode) {
            $message = \sprintf('Asset "%s" not found in manifest "%s".', $path, $this->manifest_path);
            $alternatives = $this->find_alternatives($path, $this->manifest_data);
            if (\count($alternatives) > 0) {
                $message .= \sprintf(' Did you mean one of these? "%s".', implode('", "', $alternatives));
            }
            throw new Asset_Not_Found_Exception($message, $alternatives);
        }
        return null;
    }
    private function find_alternatives(string $path, array $manifest_data): array
    {
        $path = strtolower($path);
        $alternatives = [];
        foreach ($manifest_data as $key => $value) {
            $lev = levenshtein($path, strtolower((string) $key));
            if ($lev <= \strlen($path) / 3 || false !== stripos((string) $key, $path)) {
                $alternatives[$key] = isset($alternatives[$key]) ? min($lev, $alternatives[$key]) : $lev;
            }
            $lev = levenshtein($path, strtolower((string) $value));
            if ($lev <= \strlen($path) / 3 || false !== stripos((string) $key, $path)) {
                $alternatives[$key] = isset($alternatives[$key]) ? min($lev, $alternatives[$key]) : $lev;
            }
        }
        asort($alternatives);
        return array_keys($alternatives);
    }
}