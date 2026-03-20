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
namespace Symfony\Component\Asset;

use Symfony\Component\Asset\Context\Context_Interface;
use Symfony\Component\Asset\Exception\InvalidArgumentException;
use Symfony\Component\Asset\Exception\LogicException;
use Symfony\Component\Asset\Version_Strategy\Version_Strategy_Interface;
/**
 * Package that adds a base URL to asset URLs in addition to a version.
 *
 * The package allows to use more than one base URLs in which case
 * it randomly chooses one for each asset; it also guarantees that
 * any given path will always use the same base URL to be nice with
 * HTTP caching mechanisms.
 *
 * When the request context is available, this package can choose the
 * best base URL to use based on the current request scheme:
 *
 *  * For HTTP request, it chooses between all base URLs;
 *  * For HTTPs requests, it chooses between HTTPs base URLs and relative protocol URLs
 *    or falls back to any base URL if no secure ones are available.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class Url_Package extends Package
{
    private array $base_urls = [];
    private ?self $ssl_package = null;
    /**
     * @param string|string[] $baseUrls Base asset URLs
     */
    public function __construct(string|array $base_urls, Version_Strategy_Interface $version_strategy, ?Context_Interface $context = null)
    {
        parent::__construct($version_strategy, $context);
        if (!\is_array($base_urls)) {
            $base_urls = (array) $base_urls;
        }
        if (!$base_urls) {
            throw new LogicException('You must provide at least one base URL.');
        }
        foreach ($base_urls as $base_url) {
            $this->base_urls[] = rtrim($base_url, '/');
        }
        $ssl_urls = $this->get_ssl_urls($base_urls);
        if ($ssl_urls && $base_urls !== $ssl_urls) {
            $this->ssl_package = new self($ssl_urls, $version_strategy);
        }
    }
    public function get_url(string $path): string
    {
        if ($this->is_absolute_url($path)) {
            return $path;
        }
        if (null !== $this->ssl_package && $this->get_context()->is_secure()) {
            return $this->ssl_package->get_url($path);
        }
        $url = $this->get_version_strategy()->apply_version($path);
        if ($this->is_absolute_url($url)) {
            return $url;
        }
        if ($url && '/' != $url[0]) {
            $url = '/' . $url;
        }
        return $this->get_base_url($path) . $url;
    }
    /**
     * Returns the base URL for a path.
     */
    public function get_base_url(string $path): string
    {
        if (1 === \count($this->base_urls)) {
            return $this->base_urls[0];
        }
        return $this->base_urls[$this->choose_base_url($path)];
    }
    /**
     * Determines which base URL to use for the given path.
     *
     * Override this method to change the default distribution strategy.
     * This method should always return the same base URL index for a given path.
     */
    protected function choose_base_url(string $path): int
    {
        return abs(crc32($path)) % \count($this->base_urls);
    }
    private function get_ssl_urls(array $urls): array
    {
        $ssl_urls = [];
        foreach ($urls as $url) {
            if (str_starts_with((string) $url, 'https://') || str_starts_with((string) $url, '//') || '' === $url) {
                $ssl_urls[] = $url;
            } elseif (!parse_url((string) $url, \PHP_URL_SCHEME)) {
                throw new InvalidArgumentException(\sprintf('"%s" is not a valid URL.', $url));
            }
        }
        return $ssl_urls;
    }
}