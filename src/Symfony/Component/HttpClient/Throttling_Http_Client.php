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

use Symfony\Component\Rate_Limiter\Limiter_Interface;
use Symfony\Contracts\Http_Client\Http_Client_Interface;
use Symfony\Contracts\Http_Client\Response_Interface;
use Symfony\Contracts\Service\Reset_Interface;
/**
 * Limits the number of requests within a certain period.
 */
class Throttling_Http_Client implements Http_Client_Interface, Reset_Interface
{
    use Decorator_Trait {
        reset as private traitReset;
    }
    public function __construct(Http_Client_Interface $client, private readonly Limiter_Interface $rate_limiter)
    {
        $this->client = $client;
    }
    public function request(string $method, string $url, array $options = []): Response_Interface
    {
        $response = $this->client->request($method, $url, $options);
        if (0 < $wait_duration = $this->rate_limiter->reserve()->get_wait_duration()) {
            $response->get_info('pause_handler')($wait_duration);
        }
        return $response;
    }
    public function reset(): void
    {
        $this->trait_reset();
        $this->rate_limiter->reset();
    }
}