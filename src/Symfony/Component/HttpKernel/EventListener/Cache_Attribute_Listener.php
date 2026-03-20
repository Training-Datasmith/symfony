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

use Symfony\Component\Event_Dispatcher\Event_Subscriber_Interface;
use Symfony\Component\Expression_Language\Expression;
use Symfony\Component\Expression_Language\Expression_Language;
use Symfony\Component\Http_Foundation\Header_Bag;
use Symfony\Component\Http_Foundation\Request;
use Symfony\Component\Http_Foundation\Response;
use Symfony\Component\Http_Kernel\Attribute\Cache;
use Symfony\Component\Http_Kernel\Event\Controller_Arguments_Event;
use Symfony\Component\Http_Kernel\Event\Controller_Attribute_Event;
use Symfony\Component\Http_Kernel\Event\Response_Event;
use Symfony\Component\Http_Kernel\Kernel_Events;
/**
 * Handles HTTP cache headers configured via the Cache attribute.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class Cache_Attribute_Listener implements Event_Subscriber_Interface
{
    public function __construct(private ?Expression_Language $expression_language = null)
    {
    }
    public function on_kernel_controller_attribute(Controller_Attribute_Event $event): void
    {
        $cache = $event->attribute;
        $kernel_event = $event->kernel_event;
        $request = $event->kernel_event->get_request();
        if ($kernel_event instanceof Controller_Arguments_Event) {
            if (null !== $variables = $this->get_variables($cache, $request, $kernel_event)) {
                $cache->variables = $variables;
            }
            $this->process_attribute_before_controller($cache, $request, $kernel_event);
            return;
        }
        if ($kernel_event instanceof Response_Event) {
            $response = $kernel_event->get_response();
            // http://tools.ietf.org/html/draft-ietf-httpbis-p4-conditional-12#section-3.1
            if (!\in_array($response->get_status_code(), [200, 203, 300, 301, 302, 304, 404, 410], true)) {
                return;
            }
            $this->process_attribute_after_controller($cache, $request, $response);
            return;
        }
    }
    /**
     * @internal since Symfony 8.1, use onKernelControllerAttribute() instead
     */
    public function on_kernel_controller_arguments(Controller_Arguments_Event $event): void
    {
        $request = $event->get_request();
        /** @var Cache[] $attributes */
        if (!$attributes = $request->attributes->get('_cache') ?? $event->get_attributes(Cache::class)) {
            return;
        }
        $request->attributes->set('_cache', $attributes);
        $variables = null;
        foreach ($attributes as $cache) {
            if (null !== $variables ??= $this->get_variables($cache, $request, $event)) {
                $cache->variables = $variables;
            }
            $this->process_attribute_before_controller($cache, $request, $event);
        }
    }
    /**
     * @internal since Symfony 8.1, use onKernelControllerAttribute() instead
     */
    public function on_kernel_response(Response_Event $event): void
    {
        $request = $event->get_request();
        /** @var Cache[] $attributes */
        if (!\is_array($attributes = $request->attributes->get('_cache'))) {
            return;
        }
        $response = $event->get_response();
        // http://tools.ietf.org/html/draft-ietf-httpbis-p4-conditional-12#section-3.1
        if (!\in_array($response->get_status_code(), [200, 203, 300, 301, 302, 304, 404, 410], true)) {
            return;
        }
        $has_vary = null;
        $has_cache_control_directive = null;
        for ($i = \count($attributes) - 1; 0 <= $i; --$i) {
            $this->process_attribute_after_controller($attributes[$i], $request, $response, $has_vary, $has_cache_control_directive);
        }
    }
    public static function get_subscribed_events(): array
    {
        if (!class_exists(Controller_Attributes_Listener::class, false)) {
            return [Kernel_Events::CONTROLLER_ARGUMENTS => ['onKernelControllerArguments', 10], Kernel_Events::RESPONSE => ['onKernelResponse', -10]];
        }
        return [Kernel_Events::CONTROLLER_ARGUMENTS . '.' . Cache::class => 'onKernelControllerAttribute', Kernel_Events::RESPONSE . '.' . Cache::class => 'onKernelControllerAttribute'];
    }
    public function reset(): void
    {
    }
    private function process_attribute_before_controller(Cache $cache, Request $request, Controller_Arguments_Event $event): void
    {
        if (!\is_bool($cache->if)) {
            if (!\is_bool($if = $this->evaluate($cache->if, $cache->variables))) {
                throw new \TypeError(\sprintf('The value of the "$if" option of the "%s" attribute must evaluate to a boolean, "%s" given.', Cache::class, get_debug_type($if)));
            }
            $cache->if = $if;
        }
        if (!$cache->if) {
            return;
        }
        $response = null;
        if (null !== $cache->last_modified && !$cache->last_modified instanceof \DateTimeInterface) {
            $last_modified = $this->evaluate($cache->last_modified, $cache->variables);
            ($response ??= new Response())->set_last_modified($last_modified);
            $cache->last_modified = $last_modified;
        }
        if (null !== $cache->etag) {
            $etag = hash('sha256', (string) $this->evaluate($cache->etag, $cache->variables));
            ($response ??= new Response())->set_etag($etag);
            $cache->etag = $etag;
        }
        if ($response?->is_not_modified($request)) {
            $event->set_controller(static fn(): \Symfony\Component\Http_Foundation\Response => $response);
            $event->stop_propagation();
        }
    }
    private function process_attribute_after_controller(Cache $cache, Request $request, Response $response, ?bool &$has_vary = null, ?callable &$has_cache_control_directive = null): void
    {
        if (!$cache->if) {
            return;
        }
        // Check if the response has a Vary header that should be considered, ignoring cases where
        // it's only 'Accept-Language' and the request has the '_vary_by_language' attribute
        $has_vary ??= ['Accept-Language'] === $response->get_vary() ? !$request->attributes->get('_vary_by_language') : $response->has_vary();
        // Check if cache-control directive was set manually in cacheControl (not auto computed)
        $has_cache_control_directive ??= new class($response->headers) extends Header_Bag
        {
            public function __construct(private readonly parent $header_bag)
            {
            }
            public function __invoke(string $key): bool
            {
                return \array_key_exists($key, $this->header_bag->cache_control);
            }
        };
        if (null !== $cache->last_modified && !$response->headers->has('Last-Modified')) {
            $response->set_last_modified($cache->last_modified);
        }
        if (null !== $cache->etag && !$response->headers->has('ETag')) {
            $response->set_etag($cache->etag);
        }
        if (null !== $cache->smaxage && !$has_cache_control_directive('s-maxage')) {
            $response->set_shared_max_age($this->to_seconds($cache->smaxage));
        }
        if ($cache->must_revalidate) {
            $response->headers->add_cache_control_directive('must-revalidate');
        }
        if (null !== $cache->maxage && !$has_cache_control_directive('max-age')) {
            $response->set_max_age($this->to_seconds($cache->maxage));
        }
        if (null !== $cache->max_stale && !$has_cache_control_directive('max-stale')) {
            $response->headers->add_cache_control_directive('max-stale', $this->to_seconds($cache->max_stale));
        }
        if (null !== $cache->stale_while_revalidate && !$has_cache_control_directive('stale-while-revalidate')) {
            $response->headers->add_cache_control_directive('stale-while-revalidate', $this->to_seconds($cache->stale_while_revalidate));
        }
        if (null !== $cache->stale_if_error && !$has_cache_control_directive('stale-if-error')) {
            $response->headers->add_cache_control_directive('stale-if-error', $this->to_seconds($cache->stale_if_error));
        }
        if (null !== $cache->expires && !$response->headers->has('Expires')) {
            $response->set_expires(new \DateTimeImmutable('@' . strtotime($cache->expires, time())));
        }
        if (!$has_vary && $cache->vary) {
            $response->set_vary($cache->vary, false);
        }
        $has_public_or_private_cache_control_directive = \is_bool($cache->public) && ($has_cache_control_directive('public') || $has_cache_control_directive('private'));
        if (true === $cache->public && !$has_public_or_private_cache_control_directive) {
            $response->set_public();
        }
        if (false === $cache->public && !$has_public_or_private_cache_control_directive) {
            $response->set_private();
        }
        if (true === $cache->no_store) {
            $response->headers->add_cache_control_directive('no-store');
        }
        if (false === $cache->no_store) {
            $response->headers->remove_cache_control_directive('no-store');
        }
    }
    private function get_variables(Cache $cache, Request $request, Controller_Arguments_Event $event): ?array
    {
        if (\is_bool($cache->if) && null === $cache->last_modified && null === $cache->etag) {
            return null;
        }
        $controller = $event->get_controller();
        $controller = match (true) {
            \is_object($controller) && !$controller instanceof \Closure => $controller,
            \is_array($controller) && \is_object($controller[0]) => $controller[0],
            default => null,
        };
        return array_merge(['request' => $request, 'args' => $arguments = $event->get_named_arguments(), 'this' => $controller], $request->attributes->all(), $arguments);
    }
    private function evaluate(string|Expression|\Closure $closure_or_expression, array $variables): mixed
    {
        if ($closure_or_expression instanceof \Closure) {
            return $closure_or_expression($variables['args'], $variables['request'], $variables['this']);
        }
        return $this->get_expression_language()->evaluate($closure_or_expression, $variables);
    }
    private function get_expression_language(): Expression_Language
    {
        return $this->expression_language ??= class_exists(Expression_Language::class) ? new Expression_Language() : throw new \LogicException('Unable to use expressions as the Symfony ExpressionLanguage component is not installed. Try running "composer require symfony/expression-language".');
    }
    private function to_seconds(int|string $time): int
    {
        if (!is_numeric($time)) {
            $now = time();
            $time = strtotime($time, $now) - $now;
        }
        return $time;
    }
}