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
namespace Symfony\Bridge\Twig\Extension;

use Symfony\Component\Http_Foundation\Request_Stack;
use Symfony\Component\Web_Link\Generic_Link_Provider;
use Symfony\Component\Web_Link\Link;
use Twig\Extension\Abstract_Extension;
use Twig\Twig_Function;
/**
 * Twig extension for the Symfony WebLink component.
 *
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
final class Web_Link_Extension extends Abstract_Extension
{
    public function __construct(private readonly Request_Stack $request_stack)
    {
    }
    public function get_functions(): array
    {
        return [new Twig_Function('link', $this->link(...)), new Twig_Function('preload', $this->preload(...)), new Twig_Function('dns_prefetch', $this->dns_prefetch(...)), new Twig_Function('preconnect', $this->preconnect(...)), new Twig_Function('prefetch', $this->prefetch(...)), new Twig_Function('prerender', $this->prerender(...))];
    }
    /**
     * Adds a "Link" HTTP header.
     *
     * @param string $rel        The relation type (e.g. "preload", "prefetch", or "dns-prefetch")
     * @param array  $attributes The attributes of this link (e.g. "['as' => true]", "['pr' => 0.5]")
     *
     * @return string The relation URI
     */
    public function link(string $uri, string $rel, array $attributes = []): string
    {
        if (!$request = $this->request_stack->get_main_request()) {
            return $uri;
        }
        $link = new Link($rel, $uri);
        foreach ($attributes as $key => $value) {
            $link = $link->with_attribute($key, $value);
        }
        $link_provider = $request->attributes->get('_links', new Generic_Link_Provider());
        $request->attributes->set('_links', $link_provider->with_link($link));
        return $uri;
    }
    /**
     * Preloads a resource.
     *
     * @param array $attributes The attributes of this link (e.g. "['as' => true]", "['crossorigin' => 'use-credentials']")
     *
     * @return string The path of the asset
     */
    public function preload(string $uri, array $attributes = []): string
    {
        return $this->link($uri, 'preload', $attributes);
    }
    /**
     * Resolves a resource origin as early as possible.
     *
     * @param array $attributes The attributes of this link (e.g. "['as' => true]", "['pr' => 0.5]")
     *
     * @return string The path of the asset
     */
    public function dns_prefetch(string $uri, array $attributes = []): string
    {
        return $this->link($uri, 'dns-prefetch', $attributes);
    }
    /**
     * Initiates a early connection to a resource (DNS resolution, TCP handshake, TLS negotiation).
     *
     * @param array $attributes The attributes of this link (e.g. "['as' => true]", "['pr' => 0.5]")
     *
     * @return string The path of the asset
     */
    public function preconnect(string $uri, array $attributes = []): string
    {
        return $this->link($uri, 'preconnect', $attributes);
    }
    /**
     * Indicates to the client that it should prefetch this resource.
     *
     * @param array $attributes The attributes of this link (e.g. "['as' => true]", "['pr' => 0.5]")
     *
     * @return string The path of the asset
     */
    public function prefetch(string $uri, array $attributes = []): string
    {
        return $this->link($uri, 'prefetch', $attributes);
    }
    /**
     * Indicates to the client that it should prerender this resource.
     *
     * This feature is deprecated and superseded by the Speculation Rules API.
     *
     * @see https://developer.mozilla.org/en-US/docs/Web/HTML/Reference/Attributes/rel/prerender
     *
     * @param array $attributes The attributes of this link (e.g. "['as' => true]", "['pr' => 0.5]")
     *
     * @return string The path of the asset
     */
    public function prerender(string $uri, array $attributes = []): string
    {
        return $this->link($uri, 'prerender', $attributes);
    }
}