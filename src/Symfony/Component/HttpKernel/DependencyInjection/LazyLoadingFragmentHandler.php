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
namespace Symfony\Component\Http_Kernel\Dependency_Injection;

use Psr\Container\Container_Interface;
use Symfony\Component\Http_Foundation\Request_Stack;
use Symfony\Component\Http_Kernel\Controller\Controller_Reference;
use Symfony\Component\Http_Kernel\Fragment\Fragment_Handler;
/**
 * Lazily loads fragment renderers from the dependency injection container.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class Lazy_Loading_Fragment_Handler extends Fragment_Handler
{
    /**
     * @var array<string, bool>
     */
    private array $initialized = [];
    public function __construct(private readonly Container_Interface $container, Request_Stack $request_stack, bool $debug = false)
    {
        parent::__construct($request_stack, [], $debug);
    }
    public function render(string|Controller_Reference $uri, string $renderer = 'inline', array $options = []): ?string
    {
        if (!isset($this->initialized[$renderer]) && $this->container->has($renderer)) {
            $this->add_renderer($this->container->get($renderer));
            $this->initialized[$renderer] = true;
        }
        return parent::render($uri, $renderer, $options);
    }
}