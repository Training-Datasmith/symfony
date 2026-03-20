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
namespace Symfony\Component\Http_Client\Messenger;

use Symfony\Contracts\Http_Client\Http_Client_Interface;
use Symfony\Contracts\Http_Client\Response_Interface;
/**
 * @author Kevin Bond <kevinbond@gmail.com>
 */
class Ping_Webhook_Message_Handler
{
    public function __construct(private readonly Http_Client_Interface $http_client)
    {
    }
    public function __invoke(Ping_Webhook_Message $message): Response_Interface
    {
        $response = $this->http_client->request($message->method, $message->url, $message->options);
        $response->get_headers($message->throw);
        return $response;
    }
}