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
namespace Symfony\Component\Dom_Crawler;

/**
 * Any HTML element that can link to an URI.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
abstract class Abstract_Uri_Element
{
    protected \Dom_Element $node;
    protected ?string $method;
    /**
     * @param \DOMElement $node       A \DOMElement instance
     * @param string|null $currentUri The URI of the page where the link is embedded (or the base href)
     * @param string|null $method     The method to use for the link (GET by default)
     *
     * @throws \InvalidArgumentException if the node is not a link
     */
    public function __construct(\Dom_Element $node, protected ?string $current_uri = null, ?string $method = 'GET')
    {
        $this->set_node($node);
        $this->method = $method ? strtoupper($method) : null;
        $element_uri_is_relative = !parse_url(trim($this->get_raw_uri()), \PHP_URL_SCHEME);
        $base_uri_is_absolute = null !== $this->current_uri && \in_array(strtolower(substr($this->current_uri, 0, 4)), ['http', 'file'], true);
        if ($element_uri_is_relative && !$base_uri_is_absolute) {
            throw new \InvalidArgumentException(\sprintf('The URL of the element is relative, so you must define its base URI passing an absolute URL to the constructor of the "%s" class ("%s" was passed).', self::class, $this->current_uri));
        }
    }
    /**
     * Gets the node associated with this link.
     */
    public function get_node(): \Dom_Element
    {
        return $this->node;
    }
    /**
     * Gets the method associated with this link.
     */
    public function get_method(): string
    {
        return $this->method ?? 'GET';
    }
    /**
     * Gets the URI associated with this link.
     */
    public function get_uri(): string
    {
        return Uri_Resolver::resolve($this->get_raw_uri(), $this->current_uri);
    }
    /**
     * Returns raw URI data.
     */
    abstract protected function get_raw_uri(): string;
    /**
     * Returns the canonicalized URI path (see RFC 3986, section 5.2.4).
     *
     * @param string $path URI path
     */
    protected function canonicalize_path(string $path): string
    {
        if ('' === $path || '/' === $path) {
            return $path;
        }
        if (str_ends_with($path, '.')) {
            $path .= '/';
        }
        $output = [];
        foreach (explode('/', $path) as $segment) {
            if ('..' === $segment) {
                array_pop($output);
            } elseif ('.' !== $segment) {
                $output[] = $segment;
            }
        }
        return implode('/', $output);
    }
    /**
     * Sets current \DOMElement instance.
     *
     * @param \DOMElement $node A \DOMElement instance
     *
     * @throws \LogicException If given node is not an anchor
     */
    abstract protected function set_node(\Dom_Element $node): void;
}