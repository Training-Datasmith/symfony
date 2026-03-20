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

use Symfony\Component\Http_Client\Decorator_Trait;
use Symfony\Contracts\Http_Client\Http_Client_Interface;
use Symfony\Contracts\Http_Client\Response_Interface;
/**
 * @internal
 */
class Batch_Http_Client implements Http_Client_Interface
{
    use Decorator_Trait;
    private const BATCH_SIZE = 250;
    private \WeakMap $pending_requests;
    public function request(string $method, string $url, array $options = []): Response_Interface
    {
        $this->pending_requests ??= new \WeakMap();
        $pending_requests = [];
        foreach ($this->pending_requests as $response => $_) {
            if ($response->get_info('http_code')) {
                $this->pending_requests->offsetUnset($response);
            } else {
                $pending_requests[] = $response;
            }
        }
        if (\count($pending_requests) >= self::BATCH_SIZE) {
            foreach ($this->client->stream($pending_requests) as $response => $chunk) {
                if (!$chunk->is_timeout() && $chunk->is_first()) {
                    $response->get_status_code();
                    // ignore 3/4/5xx
                    $this->pending_requests->offsetUnset($response);
                    break;
                }
            }
        }
        $response = $this->client->request($method, $url, $options);
        $this->pending_requests[$response] = true;
        return $response;
    }
}