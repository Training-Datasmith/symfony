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
namespace Symfony\Component\Http_Kernel\Event_Listener;

use Psr\Log\Logger_Interface;
use Symfony\Component\Event_Dispatcher\Event_Subscriber_Interface;
use Symfony\Component\Http_Foundation\Request;
use Symfony\Component\Http_Foundation\Request_Stack;
use Symfony\Component\Http_Foundation\Response;
use Symfony\Component\Http_Kernel\Event\Exception_Event;
use Symfony\Component\Http_Kernel\Event\Request_Event;
use Symfony\Component\Http_Kernel\Exception\Bad_Request_Http_Exception;
use Symfony\Component\Http_Kernel\Exception\Method_Not_Allowed_Http_Exception;
use Symfony\Component\Http_Kernel\Exception\Not_Found_Http_Exception;
use Symfony\Component\Http_Kernel\Kernel;
use Symfony\Component\Http_Kernel\Kernel_Events;
use Symfony\Component\Routing\Exception\Method_Not_Allowed_Exception;
use Symfony\Component\Routing\Exception\No_Configuration_Exception;
use Symfony\Component\Routing\Exception\Resource_Not_Found_Exception;
use Symfony\Component\Routing\Matcher\Request_Matcher_Interface;
use Symfony\Component\Routing\Matcher\Url_Matcher_Interface;
use Symfony\Component\Routing\Request_Context;
use Symfony\Component\Routing\Request_Context_Aware_Interface;
/**
 * Initializes the context from the request and sets request attributes based on a matching route.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Yonel Ceruto <yonelceruto@gmail.com>
 *
 * @final
 */
class Router_Listener implements Event_Subscriber_Interface
{
    private readonly Request_Context $context;
    /**
     * @param RequestContext|null $context The RequestContext (can be null when $matcher implements RequestContextAwareInterface)
     *
     * @throws \InvalidArgumentException
     */
    public function __construct(private readonly Url_Matcher_Interface|Request_Matcher_Interface $matcher, private readonly Request_Stack $request_stack, ?Request_Context $context = null, private readonly ?Logger_Interface $logger = null, private readonly ?string $project_dir = null, private readonly bool $debug = true)
    {
        if (null === $context && !$matcher instanceof Request_Context_Aware_Interface) {
            throw new \InvalidArgumentException('You must either pass a RequestContext or the matcher must implement RequestContextAwareInterface.');
        }
        $this->context = $context ?? $matcher->get_context();
    }
    private function set_current_request(?Request $request): void
    {
        if (null !== $request) {
            try {
                $this->context->from_request($request);
            } catch (\UnexpectedValueException $e) {
                throw new Bad_Request_Http_Exception($e->get_message(), $e, $e->get_code());
            }
        }
    }
    /**
     * After a sub-request is done, we need to reset the routing context to the parent request so that the URL generator
     * operates on the correct context again.
     */
    public function on_kernel_finish_request(): void
    {
        $this->set_current_request($this->request_stack->get_parent_request());
    }
    public function on_kernel_request(Request_Event $event): void
    {
        $request = $event->get_request();
        $this->set_current_request($request);
        if ($request->attributes->has('_controller')) {
            // routing is already done
            return;
        }
        // add attributes based on the request (routing)
        try {
            // matching a request is more powerful than matching a URL path + context, so try that first
            if ($this->matcher instanceof Request_Matcher_Interface) {
                $parameters = $this->matcher->match_request($request);
            } else {
                $parameters = $this->matcher->match($request->get_path_info());
            }
            $this->logger?->info('Matched route "{route}".', ['route' => $parameters['_route'] ?? 'n/a', 'route_parameters' => $parameters, 'request_uri' => $request->get_uri(), 'method' => $request->get_method()]);
            $attributes = $parameters;
            if ($mapping = $parameters['_route_mapping'] ?? false) {
                unset($parameters['_route_mapping']);
                $mapped_attributes = [];
                $attributes = [];
                foreach ($parameters as $parameter => $value) {
                    if (!isset($mapping[$parameter])) {
                        $attribute = $parameter;
                    } elseif (\is_array($mapping[$parameter])) {
                        [$attribute, $parameter] = $mapping[$parameter];
                        $mapped_attributes[$attribute] = '';
                    } else {
                        $attribute = $mapping[$parameter];
                    }
                    if (!isset($mapped_attributes[$attribute])) {
                        $attributes[$attribute] = $value;
                        $mapped_attributes[$attribute] = $parameter;
                    } elseif ('' !== $mapped_attributes[$attribute]) {
                        $attributes[$attribute] = [$mapped_attributes[$attribute] => $attributes[$attribute], $parameter => $value];
                        $mapped_attributes[$attribute] = '';
                    } else {
                        $attributes[$attribute][$parameter] = $value;
                    }
                }
                $attributes['_route_mapping'] = $mapping;
            }
            $request->attributes->add($attributes);
            unset($parameters['_route'], $parameters['_controller']);
            $request->attributes->set('_route_params', $parameters);
        } catch (Resource_Not_Found_Exception $e) {
            $message = \sprintf('No route found for "%s %s"', $request->get_method(), $request->get_uri_for_path($request->get_path_info()));
            if ($referer = $request->headers->get('referer')) {
                $message .= \sprintf(' (from "%s")', $referer);
            }
            throw new Not_Found_Http_Exception($message, $e);
        } catch (Method_Not_Allowed_Exception $e) {
            $message = \sprintf('No route found for "%s %s": Method Not Allowed (Allow: %s)', $request->get_method(), $request->get_uri_for_path($request->get_path_info()), implode(', ', $e->get_allowed_methods()));
            throw new Method_Not_Allowed_Http_Exception($e->get_allowed_methods(), $message, $e);
        }
    }
    public function on_kernel_exception(Exception_Event $event): void
    {
        if (!$this->debug || !($e = $event->get_throwable()) instanceof Not_Found_Http_Exception) {
            return;
        }
        if ($e->get_previous() instanceof No_Configuration_Exception) {
            $event->set_response($this->create_welcome_response());
        }
    }
    public static function get_subscribed_events(): array
    {
        return [Kernel_Events::REQUEST => [['onKernelRequest', 32]], Kernel_Events::FINISH_REQUEST => [['onKernelFinishRequest', 0]], Kernel_Events::EXCEPTION => ['onKernelException', -64]];
    }
    private function create_welcome_response(): Response
    {
        $version = Kernel::VERSION;
        $project_dir = realpath((string) $this->project_dir) . \DIRECTORY_SEPARATOR;
        $doc_version = substr(Kernel::VERSION, 0, 3);
        ob_start();
        include \dirname(__DIR__) . '/Resources/welcome.html.php';
        return new Response(ob_get_clean(), Response::HTTP_NOT_FOUND);
    }
}