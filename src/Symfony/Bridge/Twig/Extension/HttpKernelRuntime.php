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

use Symfony\Component\Http_Kernel\Controller\Controller_Reference;
use Symfony\Component\Http_Kernel\Fragment\Fragment_Handler;
use Symfony\Component\Http_Kernel\Fragment\Fragment_Uri_Generator_Interface;
/**
 * Provides integration with the HttpKernel component.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
final readonly class Http_Kernel_Runtime
{
    public function __construct(private Fragment_Handler $handler, private ?Fragment_Uri_Generator_Interface $fragment_uri_generator = null)
    {
    }
    /**
     * Renders a fragment.
     *
     * @see FragmentHandler::render()
     */
    public function render_fragment(string|Controller_Reference $uri, array $options = []): string
    {
        $strategy = $options['strategy'] ?? 'inline';
        unset($options['strategy']);
        return $this->handler->render($uri, $strategy, $options);
    }
    /**
     * Renders a fragment.
     *
     * @see FragmentHandler::render()
     */
    public function render_fragment_strategy(string $strategy, string|Controller_Reference $uri, array $options = []): string
    {
        return $this->handler->render($uri, $strategy, $options);
    }
    public function generate_fragment_uri(Controller_Reference $controller, bool $absolute = false, bool $strict = true, bool $sign = true): string
    {
        if (null === $this->fragment_uri_generator) {
            throw new \LogicException(\sprintf('An instance of "%s" must be provided to use "%s()".', Fragment_Uri_Generator_Interface::class, __METHOD__));
        }
        return $this->fragment_uri_generator->generate($controller, null, $absolute, $strict, $sign);
    }
}