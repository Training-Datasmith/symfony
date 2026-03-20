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
namespace Symfony\Bundle\Web_Profiler_Bundle\Event_Listener;

use Symfony\Bundle\Full_Stack;
use Symfony\Bundle\Web_Profiler_Bundle\Csp\Content_Security_Policy_Handler;
use Symfony\Component\Event_Dispatcher\Event_Subscriber_Interface;
use Symfony\Component\Http_Foundation\Event_Stream_Response;
use Symfony\Component\Http_Foundation\Request;
use Symfony\Component\Http_Foundation\Response;
use Symfony\Component\Http_Foundation\Server_Event;
use Symfony\Component\Http_Foundation\Session\Flash\Auto_Expire_Flash_Bag;
use Symfony\Component\Http_Foundation\Streamed_Response;
use Symfony\Component\Http_Kernel\Data_Collector\Dump_Data_Collector;
use Symfony\Component\Http_Kernel\Event\Response_Event;
use Symfony\Component\Http_Kernel\Kernel_Events;
use Symfony\Component\Routing\Generator\Url_Generator_Interface;
use Twig\Environment;
/**
 * WebDebugToolbarListener injects the Web Debug Toolbar.
 *
 * The onKernelResponse method must be connected to the kernel.response event.
 *
 * The WDT is only injected on well-formed HTML (with a proper </body> tag).
 * This means that the WDT is never included in sub-requests or ESI requests.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @final
 */
class Web_Debug_Toolbar_Listener implements Event_Subscriber_Interface
{
    public const DISABLED = 1;
    public const ENABLED = 2;
    public function __construct(private readonly Environment $twig, private readonly bool $intercept_redirects = false, private int $mode = self::ENABLED, private readonly ?Url_Generator_Interface $url_generator = null, private readonly string $excluded_ajax_paths = '^/bundles|^/_wdt', private readonly ?Content_Security_Policy_Handler $csp_handler = null, private readonly ?Dump_Data_Collector $dump_data_collector = null, private readonly bool $ajax_replace = false)
    {
    }
    public function is_enabled(): bool
    {
        return self::DISABLED !== $this->mode;
    }
    public function set_mode(int $mode): void
    {
        if (self::DISABLED !== $mode && self::ENABLED !== $mode) {
            throw new \InvalidArgumentException(\sprintf('Invalid value provided for mode, use one of "%s::DISABLED" or "%s::ENABLED".', self::class, self::class));
        }
        $this->mode = $mode;
    }
    public function on_kernel_response(Response_Event $event): void
    {
        $response = $event->get_response();
        $request = $event->get_request();
        if ($response->headers->has('X-Debug-Token') && null !== $this->url_generator) {
            try {
                $response->headers->set('X-Debug-Token-Link', $this->url_generator->generate('_profiler', ['token' => $response->headers->get('X-Debug-Token')], Url_Generator_Interface::ABSOLUTE_URL));
            } catch (\Exception $e) {
                $response->headers->set('X-Debug-Error', $e::class . ': ' . preg_replace('/\s+/', ' ', $e->get_message()));
            }
        }
        if (!$event->is_main_request()) {
            return;
        }
        $nonces = [];
        if ($this->csp_handler) {
            if ($this->dump_data_collector?->get_dumps_count() > 0) {
                $this->csp_handler->disable_csp();
            }
            $nonces = $this->csp_handler->update_response_headers($request, $response);
        }
        // do not capture redirects or modify XML HTTP Requests
        if ($request->is_xml_http_request()) {
            if (self::ENABLED === $this->mode && $this->ajax_replace && !$response->headers->has('Symfony-Debug-Toolbar-Replace')) {
                $response->headers->set('Symfony-Debug-Toolbar-Replace', '1');
            }
            return;
        }
        if ($response->headers->has('X-Debug-Token') && $response->is_redirect() && $this->intercept_redirects && 'html' === $request->get_request_format() && $response->headers->has('Location')) {
            if ($request->has_session() && ($session = $request->get_session())->is_started() && $session->get_flash_bag() instanceof Auto_Expire_Flash_Bag) {
                // keep current flashes for one more request if using AutoExpireFlashBag
                $session->get_flash_bag()->set_all($session->get_flash_bag()->peek_all());
            }
            $content = $this->twig->render('@WebProfiler/Profiler/toolbar_redirect.html.twig', ['location' => $response->headers->get('Location'), 'host' => $request->get_scheme_and_http_host()]);
            if ($response instanceof Streamed_Response) {
                $response->set_callback(static function () use ($content): void {
                    echo $content;
                });
            } else {
                $response->set_content($content);
            }
            $response->set_status_code(200);
            $response->headers->remove('Location');
        }
        if ($response->headers->has('X-Debug-Token') && $response instanceof Event_Stream_Response) {
            $callback = $response->get_callback();
            $response->set_callback(static function () use ($callback, $response): void {
                $response->send_event(new Server_Event([$response->headers->get('X-Debug-Token') ?? '', $response->headers->get('X-Debug-Token-Link') ?? ''], 'symfony:debug:started'));
                try {
                    $callback();
                } catch (\Throwable $e) {
                    $response->send_event(new Server_Event('error', 'symfony:debug:error'));
                    throw $e;
                } finally {
                    $response->send_event(new Server_Event('-', 'symfony:debug:finished'));
                }
            });
        }
        if (self::DISABLED === $this->mode || !$response->headers->has('X-Debug-Token') || $response->is_redirection() || $response->headers->has('Content-Type') && !str_contains($response->headers->get('Content-Type') ?? '', 'html') || 'html' !== $request->get_request_format() || false !== stripos((string) $response->headers->get('Content-Disposition', ''), 'attachment;')) {
            return;
        }
        $this->inject_toolbar($response, $request, $nonces);
    }
    /**
     * Injects the web debug toolbar into the given Response.
     */
    protected function inject_toolbar(Response $response, Request $request, array $nonces): void
    {
        $response_ref = \WeakReference::create($response);
        $inject_toolbar = function (string $buffer) use ($request, $response_ref, $nonces): string {
            if (false !== $pos = strripos($buffer, '</body>')) {
                $toolbar = "\n" . str_replace("\n", '', $this->get_toolbar_html($request, $response_ref->get()->headers->get('X-Debug-Token'), $nonces)) . "\n";
                $buffer = substr($buffer, 0, $pos) . $toolbar . substr($buffer, $pos);
            }
            return $buffer;
        };
        if (!$response instanceof Streamed_Response) {
            $response->set_content($inject_toolbar($response->get_content()));
            return;
        }
        $callback = $response->get_callback();
        $response->set_callback(static function () use ($callback, $inject_toolbar): void {
            ob_start($inject_toolbar, 8);
            // length of '</body>'
            try {
                $callback(...\func_get_args());
            } finally {
                ob_end_flush();
            }
        });
    }
    private function get_toolbar_html(Request $request, ?string $debug_token, array $nonces): string
    {
        return $this->twig->render('@WebProfiler/Profiler/toolbar_js.html.twig', ['full_stack' => class_exists(Full_Stack::class), 'excluded_ajax_paths' => $this->excluded_ajax_paths, 'token' => $debug_token, 'request' => $request, 'csp_script_nonce' => $nonces['csp_script_nonce'] ?? null, 'csp_style_nonce' => $nonces['csp_style_nonce'] ?? null]);
    }
    public static function get_subscribed_events(): array
    {
        return [Kernel_Events::RESPONSE => ['onKernelResponse', -128]];
    }
}