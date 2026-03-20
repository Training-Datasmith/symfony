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
namespace Symfony\Bundle\Framework_Bundle;

use Symfony\Bundle\Framework_Bundle\Test\Test_Browser_Token;
use Symfony\Component\Browser_Kit\Cookie;
use Symfony\Component\Browser_Kit\Cookie_Jar;
use Symfony\Component\Browser_Kit\History;
use Symfony\Component\Dependency_Injection\Container_Interface;
use Symfony\Component\Http_Foundation\Request;
use Symfony\Component\Http_Foundation\Response;
use Symfony\Component\Http_Foundation\Session\Session_Interface;
use Symfony\Component\Http_Kernel\Http_Kernel_Browser;
use Symfony\Component\Http_Kernel\Kernel_Interface;
use Symfony\Component\Http_Kernel\Profiler\Profile as HttpProfile;
use Symfony\Component\Security\Core\User\User_Interface;
/**
 * Simulates a browser and makes requests to a Kernel object.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class Kernel_Browser extends Http_Kernel_Browser
{
    private bool $has_performed_request = false;
    private bool $profiler = false;
    private bool $reboot = true;
    public function __construct(Kernel_Interface $kernel, array $server = [], ?History $history = null, ?Cookie_Jar $cookie_jar = null)
    {
        parent::__construct($kernel, $server, $history, $cookie_jar);
    }
    public function get_container(): Container_Interface
    {
        $container = $this->kernel->get_container();
        return $container->has('test.service_container') ? $container->get('test.service_container') : $container;
    }
    public function get_kernel(): Kernel_Interface
    {
        return $this->kernel;
    }
    /**
     * Gets the profile associated with the current Response.
     */
    public function get_profile(): Http_Profile|false|null
    {
        if (!isset($this->response) || !$this->get_container()->has('profiler')) {
            return false;
        }
        return $this->get_container()->get('profiler')->load_profile_from_response($this->response);
    }
    public function get_session(): ?Session_Interface
    {
        $container = $this->get_container();
        if (!$container->has('session.factory')) {
            return null;
        }
        $session = $container->get('session.factory')->create_session();
        $cookie_jar = $this->get_cookie_jar();
        $cookie = $cookie_jar->get($session->get_name());
        if ($cookie instanceof Cookie) {
            $session->set_id($cookie->get_value());
        }
        $session->start();
        if (!$cookie instanceof Cookie) {
            $domains = array_unique(array_map(static fn(Cookie $cookie): string => $cookie->get_name() === $session->get_name() ? $cookie->get_domain() : '', $cookie_jar->all())) ?: [''];
            foreach ($domains as $domain) {
                $cookie_jar->set(new Cookie($session->get_name(), $session->get_id(), domain: $domain));
            }
        }
        return $session;
    }
    /**
     * Enables the profiler for the very next request.
     *
     * If the profiler is not enabled, the call to this method does nothing.
     */
    public function enable_profiler(): void
    {
        if ($this->get_container()->has('profiler')) {
            $this->profiler = true;
        }
    }
    /**
     * Disables kernel reboot between requests.
     *
     * By default, the Client reboots the Kernel for each request. This method
     * allows to keep the same kernel across requests.
     */
    public function disable_reboot(): void
    {
        $this->reboot = false;
    }
    /**
     * Enables kernel reboot between requests.
     */
    public function enable_reboot(): void
    {
        $this->reboot = true;
    }
    /**
     * @param UserInterface        $user
     * @param array<string, mixed> $tokenAttributes
     *
     * @return $this
     */
    public function login_user(object $user, string $firewall_context = 'main', array $token_attributes = []): static
    {
        if (!interface_exists(User_Interface::class)) {
            throw new \LogicException(\sprintf('"%s" requires symfony/security-core to be installed. Try running "composer require symfony/security-core".', __METHOD__));
        }
        if (!$user instanceof User_Interface) {
            throw new \LogicException(\sprintf('The first argument of "%s" must be instance of "%s", "%s" provided.', __METHOD__, User_Interface::class, get_debug_type($user)));
        }
        $token = new Test_Browser_Token($user->get_roles(), $user, $firewall_context);
        $token->set_attributes($token_attributes);
        $container = $this->get_container();
        $container->get('security.untracked_token_storage')->set_token($token);
        if (!$session = $this->get_session()) {
            return $this;
        }
        $session->set('_security_' . $firewall_context, serialize($token));
        $session->save();
        return $this;
    }
    /**
     * @param Request $request
     */
    protected function do_request(object $request): Response
    {
        // avoid shutting down the Kernel if no request has been performed yet
        // WebTestCase::createClient() boots the Kernel but do not handle a request
        if ($this->has_performed_request && $this->reboot) {
            $this->kernel->boot();
            $this->kernel->shutdown();
        } else {
            $this->has_performed_request = true;
        }
        if ($this->profiler) {
            $this->profiler = false;
            $this->kernel->boot();
            $this->get_container()->get('profiler')->enable();
        }
        return parent::do_request($request);
    }
    /**
     * @param Request $request
     */
    protected function do_request_in_process(object $request): Response
    {
        $response = parent::do_request_in_process($request);
        $this->profiler = false;
        return $response;
    }
    /**
     * Returns the script to execute when the request must be insulated.
     *
     * It assumes that the autoloader is named 'autoload.php' and that it is
     * stored in the same directory as the kernel (this is the case for the
     * Symfony Standard Edition). If this is not your case, create your own
     * client and override this method.
     *
     * @param Request $request
     */
    protected function get_script(object $request): string
    {
        $kernel = var_export(serialize($this->kernel), true);
        $request = var_export(serialize($request), true);
        $error_reporting = error_reporting();
        $requires = '';
        foreach (get_declared_classes() as $class) {
            if (str_starts_with($class, 'ComposerAutoloaderInit')) {
                $r = new \ReflectionClass($class);
                $file = \dirname($r->get_file_name(), 2) . '/autoload.php';
                if (is_file($file)) {
                    $requires .= 'require_once ' . var_export($file, true) . ";\n";
                }
            }
        }
        if (!$requires) {
            throw new \RuntimeException('Composer autoloader not found.');
        }
        $requires .= 'require_once ' . var_export((new \Reflection_Object($this->kernel))->get_file_name(), true) . ";\n";
        $profiler_code = '';
        if ($this->profiler) {
            $profiler_code = <<<'EOF'
            $container = $kernel->getContainer();
            $container = $container->has('test.service_container') ? $container->get('test.service_container') : $container;
            $container->get('profiler')->enable();
            EOF;
        }
        $code = <<<EOF
        <?php
        
        error_reporting({$error_reporting});
        
        {$requires}
        
        \$kernel = unserialize({$kernel});
        \$kernel->boot();
        {$profiler_code}
        
        \$request = unserialize({$request});
        EOF;
        return $code . $this->get_handle_script();
    }
}