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
namespace Symfony\Bundle\Framework_Bundle\Controller;

use Psr\Container\Container_Interface;
use Psr\Link\Evolvable_Link_Interface;
use Psr\Link\Link_Interface;
use Symfony\Component\Dependency_Injection\Exception\Service_Not_Found_Exception;
use Symfony\Component\Dependency_Injection\Parameter_Bag\Container_Bag_Interface;
use Symfony\Component\Form\Extension\Core\Type\Form_Type;
use Symfony\Component\Form\Flow\Form_Flow_Builder_Interface;
use Symfony\Component\Form\Flow\Form_Flow_Interface;
use Symfony\Component\Form\Flow\Form_Flow_Type_Interface;
use Symfony\Component\Form\Flow\Type\Form_Flow_Type;
use Symfony\Component\Form\Form_Builder_Interface;
use Symfony\Component\Form\Form_Factory_Interface;
use Symfony\Component\Form\Form_Interface;
use Symfony\Component\Http_Foundation\Binary_File_Response;
use Symfony\Component\Http_Foundation\Exception\Session_Not_Found_Exception;
use Symfony\Component\Http_Foundation\Json_Response;
use Symfony\Component\Http_Foundation\Redirect_Response;
use Symfony\Component\Http_Foundation\Request;
use Symfony\Component\Http_Foundation\Request_Stack;
use Symfony\Component\Http_Foundation\Response;
use Symfony\Component\Http_Foundation\Response_Header_Bag;
use Symfony\Component\Http_Foundation\Session\Flash_Bag_Aware_Session_Interface;
use Symfony\Component\Http_Foundation\Streamed_Response;
use Symfony\Component\Http_Kernel\Exception\Not_Found_Http_Exception;
use Symfony\Component\Http_Kernel\Http_Kernel_Interface;
use Symfony\Component\Routing\Generator\Url_Generator_Interface;
use Symfony\Component\Routing\Router_Interface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\Token_Storage_Interface;
use Symfony\Component\Security\Core\Authorization\Access_Decision;
use Symfony\Component\Security\Core\Authorization\Authorization_Checker_Interface;
use Symfony\Component\Security\Core\Exception\Access_Denied_Exception;
use Symfony\Component\Security\Core\User\User_Interface;
use Symfony\Component\Security\Csrf\Csrf_Token;
use Symfony\Component\Security\Csrf\Csrf_Token_Manager_Interface;
use Symfony\Component\Serializer\Serializer_Interface;
use Symfony\Component\Web_Link\Event_Listener\Add_Link_Header_Listener;
use Symfony\Component\Web_Link\Generic_Link_Provider;
use Symfony\Component\Web_Link\Http_Header_Serializer;
use Symfony\Contracts\Service\Attribute\Required;
use Symfony\Contracts\Service\Service_Subscriber_Interface;
use Twig\Environment;
/**
 * Provides shortcuts for HTTP-related features in controllers.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
abstract class Abstract_Controller implements Service_Subscriber_Interface
{
    protected Container_Interface $container;
    #[Required]
    public function set_container(Container_Interface $container): ?Container_Interface
    {
        $previous = $this->container ?? null;
        $this->container = $container;
        return $previous;
    }
    public static function get_subscribed_services(): array
    {
        return ['router' => '?' . Router_Interface::class, 'request_stack' => '?' . Request_Stack::class, 'http_kernel' => '?' . Http_Kernel_Interface::class, 'serializer' => '?' . Serializer_Interface::class, 'security.authorization_checker' => '?' . Authorization_Checker_Interface::class, 'twig' => '?' . Environment::class, 'form.factory' => '?' . Form_Factory_Interface::class, 'security.token_storage' => '?' . Token_Storage_Interface::class, 'security.csrf.token_manager' => '?' . Csrf_Token_Manager_Interface::class, 'parameter_bag' => '?' . Container_Bag_Interface::class, 'web_link.http_header_serializer' => '?' . Http_Header_Serializer::class];
    }
    /**
     * Gets a container parameter by its name.
     */
    protected function get_parameter(string $name): array|bool|string|int|float|\Unit_Enum|null
    {
        if (!$this->container->has('parameter_bag')) {
            throw new Service_Not_Found_Exception('parameter_bag.', null, null, [], \sprintf('The "%s::getParameter()" method is missing a parameter bag to work properly. Did you forget to register your controller as a service subscriber? This can be fixed either by using autoconfiguration or by manually wiring a "parameter_bag" in the service locator passed to the controller.', static::class));
        }
        return $this->container->get('parameter_bag')->get($name);
    }
    /**
     * Generates a URL from the given parameters.
     *
     * @see UrlGeneratorInterface
     */
    protected function generate_url(string $route, array $parameters = [], int $reference_type = Url_Generator_Interface::ABSOLUTE_PATH): string
    {
        return $this->container->get('router')->generate($route, $parameters, $reference_type);
    }
    /**
     * Forwards the request to another controller.
     *
     * @param string $controller The controller name (a string like "App\Controller\PostController::index" or "App\Controller\PostController" if it is invokable)
     */
    protected function forward(string $controller, array $path = [], array $query = []): Response
    {
        $request = $this->container->get('request_stack')->get_current_request();
        $path['_controller'] = $controller;
        $sub_request = $request->duplicate($query, null, $path);
        return $this->container->get('http_kernel')->handle($sub_request, Http_Kernel_Interface::SUB_REQUEST);
    }
    /**
     * Returns a RedirectResponse to the given URL.
     *
     * @param int $status The HTTP status code (302 "Found" by default)
     */
    protected function redirect(string $url, int $status = 302): Redirect_Response
    {
        return new Redirect_Response($url, $status);
    }
    /**
     * Returns a RedirectResponse to the given route with the given parameters.
     *
     * @param int $status The HTTP status code (302 "Found" by default)
     */
    protected function redirect_to_route(string $route, array $parameters = [], int $status = 302): Redirect_Response
    {
        return $this->redirect($this->generate_url($route, $parameters), $status);
    }
    /**
     * Returns a JsonResponse that uses the serializer component if enabled, or json_encode.
     *
     * @param int $status The HTTP status code (200 "OK" by default)
     */
    protected function json(mixed $data, int $status = 200, array $headers = [], array $context = []): Json_Response
    {
        if ($this->container->has('serializer')) {
            $json = $this->container->get('serializer')->serialize($data, 'json', array_merge(['json_encode_options' => Json_Response::DEFAULT_ENCODING_OPTIONS], $context));
            return new Json_Response($json, $status, $headers, true);
        }
        if (null === $data) {
            return new Json_Response('null', $status, $headers, true);
        }
        return new Json_Response($data, $status, $headers);
    }
    /**
     * Returns a BinaryFileResponse object with original or customized file name and disposition header.
     */
    protected function file(\Spl_File_Info|string $file, ?string $file_name = null, string $disposition = Response_Header_Bag::DISPOSITION_ATTACHMENT): Binary_File_Response
    {
        $response = new Binary_File_Response($file);
        $response->set_content_disposition($disposition, $file_name ?? $response->get_file()->get_filename());
        return $response;
    }
    /**
     * Adds a flash message to the current session for type.
     *
     * @throws \LogicException
     */
    protected function add_flash(string $type, mixed $message): void
    {
        try {
            $session = $this->container->get('request_stack')->get_session();
        } catch (Session_Not_Found_Exception $e) {
            throw new \LogicException('You cannot use the addFlash method if sessions are disabled. Enable them in "config/packages/framework.yaml".', 0, $e);
        }
        if (!$session instanceof Flash_Bag_Aware_Session_Interface) {
            throw new \LogicException(\sprintf('You cannot use the addFlash method because class "%s" doesn\'t implement "%s".', get_debug_type($session), Flash_Bag_Aware_Session_Interface::class));
        }
        $session->get_flash_bag()->add($type, $message);
    }
    /**
     * Checks if the attribute is granted against the current authentication token and optionally supplied subject.
     *
     * @throws \LogicException
     */
    protected function is_granted(mixed $attribute, mixed $subject = null): bool
    {
        if (!$this->container->has('security.authorization_checker')) {
            throw new \LogicException('The SecurityBundle is not registered in your application. Try running "composer require symfony/security-bundle".');
        }
        return $this->container->get('security.authorization_checker')->is_granted($attribute, $subject);
    }
    /**
     * Checks if the attribute is granted against the current authentication token and optionally supplied subject.
     */
    protected function get_access_decision(mixed $attribute, mixed $subject = null): Access_Decision
    {
        if (!$this->container->has('security.authorization_checker')) {
            throw new \LogicException('The SecurityBundle is not registered in your application. Try running "composer require symfony/security-bundle".');
        }
        $access_decision = new Access_Decision();
        $access_decision->is_granted = $this->container->get('security.authorization_checker')->is_granted($attribute, $subject, $access_decision);
        return $access_decision;
    }
    /**
     * Throws an exception unless the attribute is granted against the current authentication token and optionally
     * supplied subject.
     *
     * @throws AccessDeniedException
     */
    protected function deny_access_unless_granted(mixed $attribute, mixed $subject = null, string $message = 'Access Denied.'): void
    {
        $access_decision = $this->get_access_decision($attribute, $subject);
        $is_granted = $access_decision->is_granted;
        if (!$is_granted) {
            $e = $this->create_access_denied_exception(3 > \func_num_args() && $access_decision ? $access_decision->get_message() : $message);
            $e->set_attributes([$attribute]);
            $e->set_subject($subject);
            if ($access_decision) {
                $e->set_access_decision($access_decision);
            }
            throw $e;
        }
    }
    /**
     * Returns a rendered view.
     *
     * Forms found in parameters are auto-cast to form views.
     */
    protected function render_view(string $view, array $parameters = []): string
    {
        return $this->do_render_view($view, null, $parameters, __FUNCTION__);
    }
    /**
     * Returns a rendered block from a view.
     *
     * Forms found in parameters are auto-cast to form views.
     */
    protected function render_block_view(string $view, string $block, array $parameters = []): string
    {
        return $this->do_render_view($view, $block, $parameters, __FUNCTION__);
    }
    /**
     * Renders a view.
     *
     * If an invalid form is found in the list of parameters, a 422 status code is returned.
     * Forms found in parameters are auto-cast to form views.
     */
    protected function render(string $view, array $parameters = [], ?Response $response = null): Response
    {
        return $this->do_render($view, null, $parameters, $response, __FUNCTION__);
    }
    /**
     * Renders a block in a view.
     *
     * If an invalid form is found in the list of parameters, a 422 status code is returned.
     * Forms found in parameters are auto-cast to form views.
     */
    protected function render_block(string $view, string $block, array $parameters = [], ?Response $response = null): Response
    {
        return $this->do_render($view, $block, $parameters, $response, __FUNCTION__);
    }
    /**
     * Streams a view.
     */
    protected function stream(string $view, array $parameters = [], ?Streamed_Response $response = null): Streamed_Response
    {
        if (!$this->container->has('twig')) {
            throw new \LogicException('You cannot use the "stream" method if the Twig Bundle is not available. Try running "composer require symfony/twig-bundle".');
        }
        $twig = $this->container->get('twig');
        $callback = static function () use ($twig, $view, $parameters): void {
            $twig->display($view, $parameters);
        };
        if (null === $response) {
            return new Streamed_Response($callback);
        }
        $response->set_callback($callback);
        return $response;
    }
    /**
     * Returns a NotFoundHttpException.
     *
     * This will result in a 404 response code. Usage example:
     *
     *     throw $this->createNotFoundException('Page not found!');
     */
    protected function create_not_found_exception(string $message = 'Not Found', ?\Throwable $previous = null): Not_Found_Http_Exception
    {
        return new Not_Found_Http_Exception($message, $previous);
    }
    /**
     * Returns an AccessDeniedException.
     *
     * This will result in a 403 response code. Usage example:
     *
     *     throw $this->createAccessDeniedException('Unable to access this page!');
     *
     * @throws \LogicException If the Security component is not available
     */
    protected function create_access_denied_exception(string $message = 'Access Denied.', ?\Throwable $previous = null): Access_Denied_Exception
    {
        if (!class_exists(Access_Denied_Exception::class)) {
            throw new \LogicException('You cannot use the "createAccessDeniedException" method if the Security component is not available. Try running "composer require symfony/security-bundle".');
        }
        return new Access_Denied_Exception($message, $previous);
    }
    /**
     * Creates and returns a Form instance from the type of the form.
     *
     * @return ($type is class-string<FormFlowTypeInterface> ? FormFlowInterface : FormInterface)
     */
    protected function create_form(string $type, mixed $data = null, array $options = []): Form_Interface
    {
        return $this->container->get('form.factory')->create($type, $data, $options);
    }
    /**
     * Creates and returns a form builder instance.
     */
    protected function create_form_builder(mixed $data = null, array $options = []): Form_Builder_Interface
    {
        return $this->container->get('form.factory')->create_builder(Form_Type::class, $data, $options);
    }
    /**
     * Creates and returns a form flow builder instance.
     */
    protected function create_form_flow_builder(mixed $data = null, array $options = []): Form_Flow_Builder_Interface
    {
        return $this->container->get('form.factory')->create_builder(Form_Flow_Type::class, $data, $options);
    }
    /**
     * Get a user from the Security Token Storage.
     *
     * @throws \LogicException If SecurityBundle is not available
     *
     * @see TokenInterface::getUser()
     */
    protected function get_user(): ?User_Interface
    {
        if (!$this->container->has('security.token_storage')) {
            throw new \LogicException('The SecurityBundle is not registered in your application. Try running "composer require symfony/security-bundle".');
        }
        if (null === $token = $this->container->get('security.token_storage')->get_token()) {
            return null;
        }
        return $token->get_user();
    }
    /**
     * Checks the validity of a CSRF token.
     *
     * @param string      $id    The id used when generating the token
     * @param string|null $token The actual token sent with the request that should be validated
     */
    protected function is_csrf_token_valid(
        string $id,
        #[\Sensitive_Parameter]
        ?string $token
    ): bool
    {
        if (!$this->container->has('security.csrf.token_manager')) {
            throw new \LogicException('CSRF protection is not enabled in your application. Enable it with the "csrf_protection" key in "config/packages/framework.yaml".');
        }
        return $this->container->get('security.csrf.token_manager')->is_token_valid(new Csrf_Token($id, $token));
    }
    /**
     * Adds a Link HTTP header to the current response.
     *
     * @see https://tools.ietf.org/html/rfc5988
     */
    protected function add_link(Request $request, Link_Interface $link): void
    {
        if (!class_exists(Add_Link_Header_Listener::class)) {
            throw new \LogicException('You cannot use the "addLink" method if the WebLink component is not available. Try running "composer require symfony/web-link".');
        }
        if (null === $link_provider = $request->attributes->get('_links')) {
            $request->attributes->set('_links', new Generic_Link_Provider([$link]));
            return;
        }
        $request->attributes->set('_links', $link_provider->with_link($link));
    }
    /**
     * @param LinkInterface[] $links
     */
    protected function send_early_hints(iterable $links = [], ?Response $response = null): Response
    {
        if (!$this->container->has('web_link.http_header_serializer')) {
            throw new \LogicException('You cannot use the "sendEarlyHints" method if the WebLink component is not available. Try running "composer require symfony/web-link".');
        }
        $response ??= new Response();
        $populated_links = [];
        foreach ($links as $link) {
            if ($link instanceof Evolvable_Link_Interface && !$link->get_rels()) {
                $link = $link->with_rel('preload');
            }
            $populated_links[] = $link;
        }
        $response->headers->set('Link', $this->container->get('web_link.http_header_serializer')->serialize($populated_links), false);
        $response->send_headers(103);
        return $response;
    }
    private function do_render_view(string $view, ?string $block, array $parameters, string $method): string
    {
        if (!$this->container->has('twig')) {
            throw new \LogicException(\sprintf('You cannot use the "%s" method if the Twig Bundle is not available. Try running "composer require symfony/twig-bundle".', $method));
        }
        foreach ($parameters as $k => $v) {
            if ($v instanceof Form_Interface) {
                $parameters[$k] = $v->create_view();
            }
        }
        if (null !== $block) {
            return $this->container->get('twig')->load($view)->render_block($block, $parameters);
        }
        return $this->container->get('twig')->render($view, $parameters);
    }
    private function do_render(string $view, ?string $block, array $parameters, ?Response $response, string $method): Response
    {
        $content = $this->do_render_view($view, $block, $parameters, $method);
        $response ??= new Response();
        if (200 === $response->get_status_code()) {
            foreach ($parameters as $v) {
                if ($v instanceof Form_Interface && $v->is_submitted() && !$v->is_valid()) {
                    $response->set_status_code(422);
                    break;
                }
            }
        }
        $response->set_content($content);
        return $response;
    }
}