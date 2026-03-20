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

use Symfony\Bundle\Full_Stack;
use Symfony\Bundle\Web_Profiler_Bundle\Csp\Content_Security_Policy_Handler;
use Symfony\Bundle\Web_Profiler_Bundle\Profiler\Template_Manager;
use Symfony\Component\Http_Foundation\Binary_File_Response;
use Symfony\Component\Http_Foundation\Redirect_Response;
use Symfony\Component\Http_Foundation\Request;
use Symfony\Component\Http_Foundation\Response;
use Symfony\Component\Http_Foundation\Session\Flash\Auto_Expire_Flash_Bag;
use Symfony\Component\Http_Kernel\Data_Collector\Dump_Data_Collector;
use Symfony\Component\Http_Kernel\Data_Collector\Exception_Data_Collector;
use Symfony\Component\Http_Kernel\Exception\Not_Found_Http_Exception;
use Symfony\Component\Http_Kernel\Profiler\Profiler;
use Symfony\Component\Routing\Generator\Url_Generator_Interface;
use Twig\Environment;
/**
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @internal
 */
class Profiler_Controller
{
    private Template_Manager $template_manager;
    public function __construct(private readonly Url_Generator_Interface $generator, private readonly ?Profiler $profiler, private readonly Environment $twig, private readonly array $templates, private readonly ?Content_Security_Policy_Handler $csp_handler = null, private readonly ?string $base_dir = null)
    {
    }
    /**
     * Redirects to the last profiles.
     *
     * @throws NotFoundHttpException
     */
    public function home_action(): Redirect_Response
    {
        $this->deny_access_if_profiler_disabled();
        return new Redirect_Response($this->generator->generate('_profiler_search_results', ['token' => 'empty', 'limit' => 10]), 302, ['Content-Type' => 'text/html']);
    }
    /**
     * Renders a profiler panel for the given token.
     *
     * @throws NotFoundHttpException
     */
    public function panel_action(Request $request, string $token): Response
    {
        $this->deny_access_if_profiler_disabled();
        $this->csp_handler?->disable_csp();
        $panel = $request->query->get('panel');
        $page = $request->query->get('page', 'home');
        $profile_type = $request->query->get('type', 'request');
        if ('latest' === $token && $latest = current($this->profiler->find(null, null, 1, null, null, null, null, static fn($profile): bool => $profile_type === $profile['virtual_type']))) {
            $token = $latest['token'];
        }
        if (!$profile = $this->profiler->load_profile($token)) {
            return $this->render_with_csp_nonces($request, '@WebProfiler/Profiler/info.html.twig', ['about' => 'no_token', 'token' => $token, 'request' => $request, 'profile_type' => $profile_type]);
        }
        $profile_type = $profile->get_virtual_type() ?? 'request';
        if (null === $panel) {
            $panel = $profile_type;
            foreach ($profile->get_collectors() as $collector) {
                if ($collector instanceof Exception_Data_Collector && $collector->has_exception()) {
                    $panel = $collector->get_name();
                    break;
                }
                if ($collector instanceof Dump_Data_Collector && $collector->get_dumps_count() > 0) {
                    $panel = $collector->get_name();
                }
            }
        }
        if (!$profile->has_collector($panel)) {
            throw new Not_Found_Http_Exception(\sprintf('Panel "%s" is not available for token "%s".', $panel, $token));
        }
        return $this->render_with_csp_nonces($request, $this->get_template_manager()->get_name($profile, $panel), [
            'token' => $token,
            'profile' => $profile,
            'collector' => $profile->get_collector($panel),
            'panel' => $panel,
            'page' => $page,
            'request' => $request,
            'templates' => $this->get_template_manager()->get_names($profile),
            'is_ajax' => $request->is_xml_http_request(),
            'profiler_markup_version' => 3,
            // 1 = original profiler, 2 = Symfony 2.8+ profiler, 3 = Symfony 6.2+ profiler
            'profile_type' => $profile_type,
        ]);
    }
    /**
     * Renders the Web Debug Toolbar.
     *
     * @throws NotFoundHttpException
     */
    public function toolbar_action(Request $request, ?string $token = null): Response
    {
        if (null === $this->profiler) {
            throw new Not_Found_Http_Exception('The profiler must be enabled.');
        }
        if (!$request->attributes->get_boolean('_stateless') && $request->has_session() && ($session = $request->get_session())->is_started() && $session->get_flash_bag() instanceof Auto_Expire_Flash_Bag) {
            // keep current flashes for one more request if using AutoExpireFlashBag
            $session->get_flash_bag()->set_all($session->get_flash_bag()->peek_all());
        }
        if ('empty' === $token || null === $token) {
            return new Response('', 200, ['Content-Type' => 'text/html']);
        }
        $this->profiler->disable();
        if (!$profile = $this->profiler->load_profile($token)) {
            return new Response('', 404, ['Content-Type' => 'text/html']);
        }
        $url = null;
        try {
            $url = $this->generator->generate('_profiler', ['token' => $token], Url_Generator_Interface::ABSOLUTE_URL);
        } catch (\Exception) {
            // the profiler is not enabled
        }
        return $this->render_with_csp_nonces($request, '@WebProfiler/Profiler/toolbar.html.twig', ['full_stack' => class_exists(Full_Stack::class), 'request' => $request, 'profile' => $profile, 'templates' => $this->get_template_manager()->get_names($profile), 'profiler_url' => $url, 'token' => $token, 'profiler_markup_version' => 3]);
    }
    /**
     * Renders the Web Debug Toolbar stylesheet.
     *
     * @throws NotFoundHttpException
     */
    public function toolbar_stylesheet_action(): Response
    {
        $this->deny_access_if_profiler_disabled();
        $this->csp_handler?->disable_csp();
        return new Response($this->twig->render('@WebProfiler/Profiler/toolbar.css.twig'), 200, ['Content-Type' => 'text/css', 'Cache-Control' => 'max-age=600, private']);
    }
    /**
     * Renders the profiler search bar.
     *
     * @throws NotFoundHttpException
     */
    public function search_bar_action(Request $request): Response
    {
        $this->deny_access_if_profiler_disabled();
        $this->csp_handler?->disable_csp();
        $session = null;
        if (!$request->attributes->get_boolean('_stateless') && $request->has_session()) {
            $session = $request->get_session();
        }
        return new Response($this->twig->render('@WebProfiler/Profiler/search.html.twig', ['token' => $request->query->get('token', $session?->get('_profiler_search_token')), 'ip' => $request->query->get('ip', $session?->get('_profiler_search_ip')), 'method' => $request->query->get('method', $session?->get('_profiler_search_method')), 'status_code' => $request->query->get('status_code', $session?->get('_profiler_search_status_code')), 'url' => $request->query->get('url', $session?->get('_profiler_search_url')), 'start' => $request->query->get('start', $session?->get('_profiler_search_start')), 'end' => $request->query->get('end', $session?->get('_profiler_search_end')), 'limit' => $request->query->get('limit', $session?->get('_profiler_search_limit')), 'request' => $request, 'profile_type' => $request->query->get('type', $session?->get('_profiler_search_type', 'request'))]), 200, ['Content-Type' => 'text/html']);
    }
    /**
     * Renders the search results.
     *
     * @throws NotFoundHttpException
     */
    public function search_results_action(Request $request, string $token): Response
    {
        $this->deny_access_if_profiler_disabled();
        $this->csp_handler?->disable_csp();
        $profile = $this->profiler->load_profile($token);
        $ip = $request->query->get('ip');
        $method = $request->query->get('method');
        $status_code = $request->query->get('status_code');
        $url = $request->query->get('url');
        $start = $request->query->get('start');
        $end = $request->query->get('end');
        $limit = $request->query->get('limit');
        $profile_type = $request->query->get('type', 'request');
        return $this->render_with_csp_nonces($request, '@WebProfiler/Profiler/results.html.twig', ['request' => $request, 'token' => $token, 'profile' => $profile, 'tokens' => $this->profiler->find($ip, $url, $limit, $method, $start, $end, $status_code, static fn($profile): bool => $profile_type === $profile['virtual_type']), 'ip' => $ip, 'method' => $method, 'status_code' => $status_code, 'url' => $url, 'start' => $start, 'end' => $end, 'limit' => $limit, 'panel' => null, 'profile_type' => $profile_type]);
    }
    /**
     * Narrows the search bar.
     *
     * @throws NotFoundHttpException
     */
    public function search_action(Request $request): Response
    {
        $this->deny_access_if_profiler_disabled();
        $ip = $request->query->get('ip');
        $method = $request->query->get('method');
        $status_code = $request->query->get('status_code');
        $url = $request->query->get('url');
        $start = $request->query->get('start');
        $end = $request->query->get('end');
        $limit = $request->query->get('limit');
        $token = $request->query->get('token');
        $profile_type = $request->query->get('type', 'request');
        if (!$request->attributes->get_boolean('_stateless') && $request->has_session()) {
            $session = $request->get_session();
            $session->set('_profiler_search_ip', $ip);
            $session->set('_profiler_search_method', $method);
            $session->set('_profiler_search_status_code', $status_code);
            $session->set('_profiler_search_url', $url);
            $session->set('_profiler_search_start', $start);
            $session->set('_profiler_search_end', $end);
            $session->set('_profiler_search_limit', $limit);
            $session->set('_profiler_search_token', $token);
            $session->set('_profiler_search_type', $profile_type);
        }
        if ($token) {
            return new Redirect_Response($this->generator->generate('_profiler', ['token' => $token]), 302, ['Content-Type' => 'text/html']);
        }
        $tokens = $this->profiler->find($ip, $url, $limit, $method, $start, $end, $status_code, static fn($profile): bool => $profile_type === $profile['virtual_type']);
        return new Redirect_Response($this->generator->generate('_profiler_search_results', ['token' => $tokens ? $tokens[0]['token'] : 'empty', 'ip' => $ip, 'method' => $method, 'status_code' => $status_code, 'url' => $url, 'start' => $start, 'end' => $end, 'limit' => $limit, 'type' => $profile_type]), 302, ['Content-Type' => 'text/html']);
    }
    /**
     * Displays the PHP info.
     *
     * @throws NotFoundHttpException
     */
    public function phpinfo_action(): Response
    {
        $this->deny_access_if_profiler_disabled();
        $this->csp_handler?->disable_csp();
        ob_start();
        phpinfo();
        $phpinfo = ob_get_clean();
        return new Response($phpinfo, 200, ['Content-Type' => 'text/html']);
    }
    /**
     * Displays the Xdebug info.
     *
     * @throws NotFoundHttpException
     */
    public function xdebug_action(): Response
    {
        $this->deny_access_if_profiler_disabled();
        if (!\function_exists('xdebug_info')) {
            throw new Not_Found_Http_Exception('Xdebug must be installed in version 3.');
        }
        $this->csp_handler?->disable_csp();
        ob_start();
        xdebug_info();
        $xdebug_info = ob_get_clean();
        return new Response($xdebug_info, 200, ['Content-Type' => 'text/html']);
    }
    /**
     * Returns the custom web fonts used in the profiler.
     *
     * @throws NotFoundHttpException
     */
    public function font_action(string $font_name): Response
    {
        $this->deny_access_if_profiler_disabled();
        if ('JetBrainsMono' !== $font_name) {
            throw new Not_Found_Http_Exception(\sprintf('Font file "%s.woff2" not found.', $font_name));
        }
        $font_file = \dirname(__DIR__) . '/Resources/fonts/' . $font_name . '.woff2';
        if (!is_file($font_file) || !is_readable($font_file)) {
            throw new Not_Found_Http_Exception(\sprintf('Cannot read font file "%s".', $font_file));
        }
        $this->profiler?->disable();
        return new Binary_File_Response($font_file, 200, ['Content-Type' => 'font/woff2']);
    }
    /**
     * Displays the source of a file.
     *
     * @throws NotFoundHttpException
     */
    public function open_action(Request $request): Response
    {
        if (null === $this->base_dir) {
            throw new Not_Found_Http_Exception('The base dir should be set.');
        }
        $this->profiler?->disable();
        $file = $request->query->get('file');
        $line = $request->query->get('line');
        $filename = $this->base_dir . \DIRECTORY_SEPARATOR . $file;
        if (preg_match("'(^|[/\\\\])\\.'", (string) $file) || !is_readable($filename)) {
            throw new Not_Found_Http_Exception(\sprintf('The file "%s" cannot be opened.', $file));
        }
        return $this->render_with_csp_nonces($request, '@WebProfiler/Profiler/open.html.twig', ['file_info' => new \Spl_File_Info($filename), 'file' => $file, 'line' => $line]);
    }
    protected function get_template_manager(): Template_Manager
    {
        return $this->template_manager ??= new Template_Manager($this->profiler, $this->twig, $this->templates);
    }
    /**
     * @throws NotFoundHttpException
     */
    private function deny_access_if_profiler_disabled(): void
    {
        if (null === $this->profiler) {
            throw new Not_Found_Http_Exception('The profiler must be enabled.');
        }
        $this->profiler->disable();
    }
    private function render_with_csp_nonces(Request $request, string $template, array $variables, int $code = 200, array $headers = ['Content-Type' => 'text/html']): Response
    {
        $response = new Response('', $code, $headers);
        $nonces = $this->csp_handler ? $this->csp_handler->get_nonces($request, $response) : [];
        $variables['csp_script_nonce'] = $nonces['csp_script_nonce'] ?? null;
        $variables['csp_style_nonce'] = $nonces['csp_style_nonce'] ?? null;
        $response->set_content($this->twig->render($template, $variables));
        return $response;
    }
}