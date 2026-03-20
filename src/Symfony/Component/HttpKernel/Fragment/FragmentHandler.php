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
namespace Symfony\Component\Http_Kernel\Fragment;

use Symfony\Component\Http_Foundation\Request_Stack;
use Symfony\Component\Http_Foundation\Response;
use Symfony\Component\Http_Foundation\Streamed_Response;
use Symfony\Component\Http_Kernel\Controller\Controller_Reference;
use Symfony\Component\Http_Kernel\Exception\Http_Exception;
/**
 * Renders a URI that represents a resource fragment.
 *
 * This class handles the rendering of resource fragments that are included into
 * a main resource. The handling of the rendering is managed by specialized renderers.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @see FragmentRendererInterface
 */
class Fragment_Handler
{
    /** @var array<string, FragmentRendererInterface> */
    private array $renderers = [];
    /**
     * @param FragmentRendererInterface[] $renderers An array of FragmentRendererInterface instances
     * @param bool                        $debug     Whether the debug mode is enabled or not
     */
    public function __construct(private readonly Request_Stack $request_stack, array $renderers = [], private readonly bool $debug = false)
    {
        foreach ($renderers as $renderer) {
            $this->add_renderer($renderer);
        }
    }
    /**
     * Adds a renderer.
     */
    public function add_renderer(Fragment_Renderer_Interface $renderer): void
    {
        $this->renderers[$renderer->get_name()] = $renderer;
    }
    /**
     * Renders a URI and returns the Response content.
     *
     * Available options:
     *
     *  * ignore_errors: true to return an empty string in case of an error
     *
     * @throws \InvalidArgumentException when the renderer does not exist
     * @throws \LogicException           when no main request is being handled
     */
    public function render(string|Controller_Reference $uri, string $renderer = 'inline', array $options = []): ?string
    {
        if (!isset($options['ignore_errors'])) {
            $options['ignore_errors'] = !$this->debug;
        }
        if (!isset($this->renderers[$renderer])) {
            throw new \InvalidArgumentException(\sprintf('The "%s" renderer does not exist.', $renderer));
        }
        if (!$request = $this->request_stack->get_current_request()) {
            throw new \LogicException('Rendering a fragment can only be done when handling a Request.');
        }
        return $this->deliver($this->renderers[$renderer]->render($uri, $request, $options));
    }
    /**
     * Delivers the Response as a string.
     *
     * When the Response is a StreamedResponse, the content is streamed immediately
     * instead of being returned.
     *
     * @return string|null The Response content or null when the Response is streamed
     *
     * @throws \RuntimeException when the Response is not successful
     */
    protected function deliver(Response $response): ?string
    {
        if (!$response->is_successful()) {
            $response_status_code = $response->get_status_code();
            throw new \RuntimeException(\sprintf('Error when rendering "%s" (Status code is %d).', $this->request_stack->get_current_request()->get_uri(), $response_status_code), 0, new Http_Exception($response_status_code));
        }
        if (!$response instanceof Streamed_Response) {
            return $response->get_content();
        }
        $response->send_content();
        return null;
    }
}