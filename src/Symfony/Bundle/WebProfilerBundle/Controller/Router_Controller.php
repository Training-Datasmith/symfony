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
namespace Symfony\Bundle\Web_Profiler_Bundle\Controller;

use Symfony\Component\Expression_Language\Expression_Function_Provider_Interface;
use Symfony\Component\Http_Foundation\Request;
use Symfony\Component\Http_Foundation\Response;
use Symfony\Component\Http_Kernel\Data_Collector\Request_Data_Collector;
use Symfony\Component\Http_Kernel\Exception\Not_Found_Http_Exception;
use Symfony\Component\Http_Kernel\Profiler\Profiler;
use Symfony\Component\Routing\Matcher\Traceable_Url_Matcher;
use Symfony\Component\Routing\Matcher\Url_Matcher_Interface;
use Symfony\Component\Routing\Route_Collection;
use Symfony\Component\Routing\Router_Interface;
use Twig\Environment;
/**
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @internal
 */
class Router_Controller
{
    /**
     * @param ExpressionFunctionProviderInterface[] $expressionLanguageProviders
     */
    public function __construct(private readonly ?Profiler $profiler, private readonly Environment $twig, private readonly ?Url_Matcher_Interface $matcher = null, private ?Route_Collection $routes = null, private readonly iterable $expression_language_providers = [])
    {
        if ($this->matcher instanceof Router_Interface) {
            $this->routes ??= $this->matcher->get_route_collection();
        }
    }
    /**
     * Renders the profiler panel for the given token.
     *
     * @throws NotFoundHttpException
     */
    public function panel_action(string $token): Response
    {
        if (null === $this->profiler) {
            throw new Not_Found_Http_Exception('The profiler must be enabled.');
        }
        $this->profiler->disable();
        if (null === $this->matcher || null === $this->routes) {
            return new Response('The Router is not enabled.', 200, ['Content-Type' => 'text/html']);
        }
        $profile = $this->profiler->load_profile($token);
        /** @var RequestDataCollector $request */
        $request = $profile->get_collector('request');
        return new Response($this->twig->render('@WebProfiler/Router/panel.html.twig', ['request' => $request, 'router' => $profile->get_collector('router'), 'traces' => $this->get_traces($request, $profile->get_method())]), 200, ['Content-Type' => 'text/html']);
    }
    /**
     * Returns the routing traces associated to the given request.
     */
    private function get_traces(Request_Data_Collector $request, string $method): array
    {
        $trace_request = new Request($request->get_request_query()->all(), $request->get_request_request()->all(), $request->get_request_attributes()->all(), $request->get_request_cookies(true)->all(), [], $request->get_request_server(true)->all());
        $context = $this->matcher->get_context();
        $context->set_method($method);
        $matcher = new Traceable_Url_Matcher($this->routes, $context);
        foreach ($this->expression_language_providers as $provider) {
            $matcher->add_expression_language_provider($provider);
        }
        return $matcher->get_traces_for_request($trace_request);
    }
}