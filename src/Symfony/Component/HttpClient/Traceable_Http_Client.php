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

use Symfony\Component\Http_Client\Response\Response_Stream;
use Symfony\Component\Http_Client\Response\Traceable_Response;
use Symfony\Component\Stopwatch\Stopwatch;
use Symfony\Contracts\Http_Client\Http_Client_Interface;
use Symfony\Contracts\Http_Client\Response_Interface;
use Symfony\Contracts\Http_Client\Response_Stream_Interface;
use Symfony\Contracts\Service\Reset_Interface;
/**
 * @author Jérémy Romey <jeremy@free-agent.fr>
 */
final class Traceable_Http_Client implements Http_Client_Interface, Reset_Interface
{
    private \ArrayObject $traced_requests;
    public function __construct(private Http_Client_Interface $client, private ?Stopwatch $stopwatch = null, private ?\Closure $disabled = null)
    {
        $this->traced_requests = new \ArrayObject();
    }
    public function request(string $method, string $url, array $options = []): Response_Interface
    {
        if ($this->disabled?->__invoke()) {
            return new Traceable_Response($this->client, $this->client->request($method, $url, $options));
        }
        $content = null;
        $trace_info = [];
        $traced_request = ['method' => $method, 'url' => $url, 'options' => $options, 'info' => &$trace_info, 'content' => &$content];
        $on_progress = $options['on_progress'] ?? null;
        if (false === ($options['extra']['trace_content'] ?? true)) {
            unset($content);
            $content = false;
            unset($traced_request['options']['body'], $traced_request['options']['json']);
        }
        $this->traced_requests[] = $traced_request;
        $options['on_progress'] = static function (int $dl_now, int $dl_size, array $info) use (&$trace_info, $on_progress): void {
            $trace_info = $info;
            if (null !== $on_progress) {
                $on_progress($dl_now, $dl_size, $info);
            }
        };
        return new Traceable_Response($this->client, $this->client->request($method, $url, $options), $content, $this->stopwatch?->start("{$method} {$url}", 'http_client'));
    }
    public function stream(Response_Interface|iterable $responses, ?float $timeout = null): Response_Stream_Interface
    {
        if ($responses instanceof Traceable_Response) {
            $responses = [$responses];
        }
        return new Response_Stream(Traceable_Response::stream($this->client, $responses, $timeout));
    }
    public function get_traced_requests(): array
    {
        return $this->traced_requests->get_array_copy();
    }
    public function reset(): void
    {
        if ($this->client instanceof Reset_Interface) {
            $this->client->reset();
        }
        $this->traced_requests->exchange_array([]);
    }
    public function with_options(array $options): static
    {
        $clone = clone $this;
        $clone->client = $this->client->with_options($options);
        return $clone;
    }
}