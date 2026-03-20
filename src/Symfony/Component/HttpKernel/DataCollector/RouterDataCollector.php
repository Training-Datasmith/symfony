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
namespace Symfony\Component\Http_Kernel\Data_Collector;

use Symfony\Component\Http_Foundation\Redirect_Response;
use Symfony\Component\Http_Foundation\Request;
use Symfony\Component\Http_Foundation\Response;
use Symfony\Component\Http_Kernel\Event\Controller_Event;
/**
 * @author Fabien Potencier <fabien@symfony.com>
 */
class Router_Data_Collector extends Data_Collector
{
    /**
     * @var \SplObjectStorage<Request, callable>
     */
    protected \Spl_Object_Storage $controllers;
    public function __construct()
    {
        $this->reset();
    }
    /**
     * @final
     */
    public function collect(Request $request, Response $response, ?\Throwable $exception = null): void
    {
        if ($response instanceof Redirect_Response) {
            $this->data['redirect'] = true;
            $this->data['url'] = $response->get_target_url();
            if ($this->controllers->offsetExists($request)) {
                $this->data['route'] = $this->guess_route($request, $this->controllers[$request]);
            }
        }
        unset($this->controllers[$request]);
    }
    public function reset(): void
    {
        $this->controllers = new \Spl_Object_Storage();
        $this->data = ['redirect' => false, 'url' => null, 'route' => null];
    }
    protected function guess_route(Request $request, string|object|array $controller): string
    {
        return 'n/a';
    }
    /**
     * Remembers the controller associated to each request.
     */
    public function on_kernel_controller(Controller_Event $event): void
    {
        $this->controllers[$event->get_request()] = $event->get_controller();
    }
    /**
     * @return bool Whether this request will result in a redirect
     */
    public function get_redirect(): bool
    {
        return $this->data['redirect'];
    }
    public function get_target_url(): ?string
    {
        return $this->data['url'];
    }
    public function get_target_route(): ?string
    {
        return $this->data['route'];
    }
    public function get_name(): string
    {
        return 'router';
    }
}