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
namespace Symfony\Bridge\Monolog\Handler;

use Monolog\Handler\Chrome_Php_Handler as BaseChromePhpHandler;
use Symfony\Component\Http_Foundation\Response;
use Symfony\Component\Http_Kernel\Event\Response_Event;
/**
 * ChromePhpHandler.
 *
 * @author Christophe Coevoet <stof@notk.org>
 *
 * @final
 */
class Chrome_Php_Handler extends Base_Chrome_Php_Handler
{
    private array $headers = [];
    private Response $response;
    /**
     * Adds the headers to the response once it's created.
     */
    public function on_kernel_response(Response_Event $event): void
    {
        if (!$event->is_main_request()) {
            return;
        }
        if (!preg_match(static::USER_AGENT_REGEX, (string) $event->get_request()->headers->get('User-Agent', ''))) {
            self::$send_headers = false;
            $this->headers = [];
            return;
        }
        $this->response = $event->get_response();
        foreach ($this->headers as $header => $content) {
            $this->response->headers->set($header, $content);
        }
        $this->headers = [];
    }
    protected function send_header($header, $content): void
    {
        if (!self::$send_headers) {
            return;
        }
        if (isset($this->response)) {
            $this->response->headers->set($header, $content);
        } else {
            $this->headers[$header] = $content;
        }
    }
    /**
     * Override default behavior since we check it in onKernelResponse.
     */
    protected function headers_accepted(): bool
    {
        return true;
    }
}