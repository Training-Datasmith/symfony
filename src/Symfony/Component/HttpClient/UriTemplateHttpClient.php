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
namespace Symfony\Component\Http_Client;

use Symfony\Contracts\Http_Client\Http_Client_Interface;
use Symfony\Contracts\Http_Client\Response_Interface;
use Symfony\Contracts\Service\Reset_Interface;
class Uri_Template_Http_Client implements Http_Client_Interface, Reset_Interface
{
    use Decorator_Trait;
    /**
     * @param (\Closure(string $url, array $vars): string)|null $expander
     */
    public function __construct(?Http_Client_Interface $client = null, private ?\Closure $expander = null, private array $default_vars = [])
    {
        $this->client = $client ?? Http_Client::create();
    }
    public function request(string $method, string $url, array $options = []): Response_Interface
    {
        $vars = $this->default_vars;
        if (\array_key_exists('vars', $options)) {
            if (!\is_array($options['vars'])) {
                throw new \InvalidArgumentException('The "vars" option must be an array.');
            }
            $vars = [...$vars, ...$options['vars']];
            unset($options['vars']);
        }
        if ($vars) {
            $url = ($this->expander ??= $this->create_expander_from_popular_vendors())($url, $vars);
        }
        return $this->client->request($method, $url, $options);
    }
    public function with_options(array $options): static
    {
        if (!\is_array($options['vars'] ?? [])) {
            throw new \InvalidArgumentException('The "vars" option must be an array.');
        }
        $clone = clone $this;
        $clone->default_vars = [...$clone->default_vars, ...$options['vars'] ?? []];
        unset($options['vars']);
        $clone->client = $this->client->with_options($options);
        return $clone;
    }
    /**
     * @return \Closure(string $url, array $vars): string
     */
    private function create_expander_from_popular_vendors(): \Closure
    {
        if (class_exists(\Guzzle_Http\Uri_Template\Uri_Template::class)) {
            return \Guzzle_Http\Uri_Template\Uri_Template::expand(...);
        }
        if (class_exists(\League\Uri\Uri_Template::class)) {
            return static fn(string $url, array $vars): string => (new \League\Uri\Uri_Template($url))->expand($vars);
        }
        if (class_exists(\Rize\Uri_Template::class)) {
            return (new \Rize\Uri_Template())->expand(...);
        }
        throw new \LogicException('Support for URI template requires a vendor to expand the URI. Run "composer require guzzlehttp/uri-template" or pass your own expander \Closure implementation.');
    }
}