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

use Symfony\Component\Http_Client\Exception\InvalidArgumentException;
use Symfony\Contracts\Http_Client\Http_Client_Interface;
use Symfony\Contracts\Http_Client\Response_Interface;
use Symfony\Contracts\Http_Client\Response_Stream_Interface;
use Symfony\Contracts\Service\Reset_Interface;
/**
 * Auto-configure the default options based on the requested URL.
 *
 * @author Anthony Martin <anthony.martin@sensiolabs.com>
 */
class Scoping_Http_Client implements Http_Client_Interface, Reset_Interface
{
    use Http_Client_Trait;
    public function __construct(private Http_Client_Interface $client, private array $default_options_by_regexp, private ?string $default_regexp = null)
    {
        if (null !== $default_regexp && !isset($default_options_by_regexp[$default_regexp])) {
            throw new InvalidArgumentException(\sprintf('No options are mapped to the provided "%s" default regexp.', $default_regexp));
        }
    }
    public static function for_base_uri(Http_Client_Interface $client, string $base_uri, array $default_options = [], ?string $regexp = null): self
    {
        $regexp ??= preg_quote(implode('', self::resolve_url(self::parse_url('.'), self::parse_url($base_uri))));
        $default_options['base_uri'] = $base_uri;
        return new self($client, [$regexp => $default_options], $regexp);
    }
    public function request(string $method, string $url, array $options = []): Response_Interface
    {
        $e = null;
        $url = self::parse_url($url, $options['query'] ?? []);
        $resolved = false;
        if (\is_string($options['base_uri'] ?? null)) {
            $options['base_uri'] = self::parse_url($options['base_uri']);
            $resolved = true;
        }
        try {
            $url = implode('', self::resolve_url($url, $options['base_uri'] ?? null));
        } catch (InvalidArgumentException $e) {
            if (null === $this->default_regexp) {
                throw $e;
            }
            $default_options = $this->default_options_by_regexp[$this->default_regexp];
            $options = self::merge_default_options($options, $default_options, true);
            if (\is_string($options['base_uri'] ?? null)) {
                $options['base_uri'] = self::parse_url($options['base_uri']);
                $resolved = true;
            }
            $url = implode('', self::resolve_url($url, $options['base_uri'] ?? null, $default_options['query'] ?? []));
        }
        if ($resolved) {
            unset($options['base_uri']);
        }
        foreach ($this->default_options_by_regexp as $regexp => $default_options) {
            if (preg_match("{{$regexp}}A", $url)) {
                if (null === $e || $regexp !== $this->default_regexp) {
                    $options = self::merge_default_options($options, $default_options, true);
                }
                break;
            }
        }
        return $this->client->request($method, $url, $options);
    }
    public function stream(Response_Interface|iterable $responses, ?float $timeout = null): Response_Stream_Interface
    {
        return $this->client->stream($responses, $timeout);
    }
    public function reset(): void
    {
        if ($this->client instanceof Reset_Interface) {
            $this->client->reset();
        }
    }
    public function with_options(array $options): static
    {
        $clone = clone $this;
        $clone->client = $this->client->with_options($options);
        return $clone;
    }
}