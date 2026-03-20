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
namespace Symfony\Bundle\Security_Bundle;

use Psr\Container\Container_Interface;
use Symfony\Bundle\Security_Bundle\Security\Firewall_Config;
use Symfony\Component\Http_Foundation\Request;
use Symfony\Component\Http_Foundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\Storage\Token_Storage_Interface;
use Symfony\Component\Security\Core\Authentication\Token\Token_Interface;
use Symfony\Component\Security\Core\Authorization\Access_Decision;
use Symfony\Component\Security\Core\Authorization\Authorization_Checker_Interface;
use Symfony\Component\Security\Core\Authorization\User_Authorization_Checker_Interface;
use Symfony\Component\Security\Core\Exception\LogicException;
use Symfony\Component\Security\Core\Exception\Logout_Exception;
use Symfony\Component\Security\Core\User\User_Interface;
use Symfony\Component\Security\Csrf\Csrf_Token;
use Symfony\Component\Security\Http\Authenticator\Authenticator_Interface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\Badge_Interface;
use Symfony\Component\Security\Http\Event\Logout_Event;
use Symfony\Component\Security\Http\Parameter_Bag_Utils;
use Symfony\Contracts\Service\Service_Provider_Interface;
/**
 * Helper class for commonly-needed security tasks.
 *
 * @author Ryan Weaver <ryan@symfonycasts.com>
 * @author Robin Chalas <robin.chalas@gmail.com>
 * @author Arnaud Frézet <arnaud@larriereguichet.fr>
 *
 * @final
 */
class Security implements Authorization_Checker_Interface, User_Authorization_Checker_Interface
{
    public function __construct(private readonly Container_Interface $container, private readonly array $authenticators = [])
    {
    }
    public function get_user(): ?User_Interface
    {
        if (!$token = $this->get_token()) {
            return null;
        }
        return $token->get_user();
    }
    /**
     * Checks if the attributes are granted against the current authentication token and optionally supplied subject.
     */
    public function is_granted(mixed $attributes, mixed $subject = null, ?Access_Decision $access_decision = null): bool
    {
        return $this->container->get('security.authorization_checker')->is_granted($attributes, $subject, $access_decision);
    }
    public function get_access_decision(mixed $attributes, mixed $subject = null): Access_Decision
    {
        $access_decision = new Access_Decision();
        $this->is_granted($attributes, $subject, $access_decision);
        return $access_decision;
    }
    /**
     * Checks if the attribute is granted against the user and optionally supplied subject.
     *
     * This should be used over isGranted() when checking permissions against a user that is not currently logged in or while in a CLI context.
     */
    public function is_granted_for_user(User_Interface $user, mixed $attribute, mixed $subject = null, ?Access_Decision $access_decision = null): bool
    {
        return $this->container->get('security.user_authorization_checker')->is_granted_for_user($user, $attribute, $subject, $access_decision);
    }
    public function get_access_decision_for_user(User_Interface $user, mixed $attributes, mixed $subject = null): Access_Decision
    {
        $access_decision = new Access_Decision();
        $this->is_granted_for_user($user, $attributes, $subject, $access_decision);
        return $access_decision;
    }
    public function get_token(): ?Token_Interface
    {
        return $this->container->get('security.token_storage')->get_token();
    }
    public function get_firewall_config(Request $request): ?Firewall_Config
    {
        return $this->container->get('security.firewall.map')->get_firewall_config($request);
    }
    /**
     * @param UserInterface        $user              The user to authenticate
     * @param string|null          $authenticatorName The authenticator name (e.g. "form_login") or service id (e.g. SomeApiKeyAuthenticator::class) - required only if multiple authenticators are configured
     * @param string|null          $firewallName      The firewall name - required only if multiple firewalls are configured
     * @param BadgeInterface[]     $badges            Badges to add to the user's passport
     * @param array<string, mixed> $attributes        Attributes to add to the user's passport
     *
     * @return Response|null The authenticator success response if any
     */
    public function login(User_Interface $user, ?string $authenticator_name = null, ?string $firewall_name = null, array $badges = [], array $attributes = []): ?Response
    {
        $request = $this->container->get('request_stack')->get_current_request();
        if (null === $request) {
            throw new LogicException('Unable to login without a request context.');
        }
        $firewall_name ??= $this->get_firewall_config($request)?->get_name();
        if (!$firewall_name) {
            throw new LogicException('Unable to login as the current route is not covered by any firewall.');
        }
        $authenticator = $this->get_authenticator($authenticator_name, $firewall_name);
        $user_checker_locator = $this->container->get('security.user_checker_locator');
        $user_checker_locator->get($firewall_name)->check_pre_auth($user);
        return $this->container->get('security.authenticator.managers_locator')->get($firewall_name)->authenticate_user($user, $authenticator, $request, $badges, $attributes);
    }
    /**
     * Logout the current user by dispatching the LogoutEvent.
     *
     * @param bool $validateCsrfToken Whether to look for a valid CSRF token based on the `logout` listener configuration
     *
     * @return Response|null The LogoutEvent's Response if any
     *
     * @throws LogoutException When $validateCsrfToken is true and the CSRF token is not found or invalid
     */
    public function logout(bool $validate_csrf_token = true): ?Response
    {
        $request = $this->container->get('request_stack')->get_main_request();
        if (null === $request) {
            throw new LogicException('Unable to logout without a request context.');
        }
        /** @var TokenStorageInterface $tokenStorage */
        $token_storage = $this->container->get('security.token_storage');
        if (!($token = $token_storage->get_token()) || !$token->get_user()) {
            throw new LogicException('Unable to logout as there is no logged-in user.');
        }
        if (!$firewall_config = $this->container->get('security.firewall.map')->get_firewall_config($request)) {
            throw new LogicException('Unable to logout as the request is not behind a firewall.');
        }
        if ($validate_csrf_token) {
            if (!$this->container->has('security.csrf.token_manager') || !$logout_config = $firewall_config->get_logout()) {
                throw new LogicException(\sprintf('Unable to logout with CSRF token validation. Either make sure that CSRF protection is enabled and "logout" is configured on the "%s" firewall, or bypass CSRF token validation explicitly by passing false to the $validateCsrfToken argument of this method.', $firewall_config->get_name()));
            }
            $csrf_token = Parameter_Bag_Utils::get_request_parameter_value($request, $logout_config['csrf_parameter']);
            if (!\is_string($csrf_token) || !$this->container->get('security.csrf.token_manager')->is_token_valid(new Csrf_Token($logout_config['csrf_token_id'], $csrf_token))) {
                throw new Logout_Exception('Invalid CSRF token.');
            }
        }
        $logout_event = new Logout_Event($request, $token);
        $this->container->get('security.firewall.event_dispatcher_locator')->get($firewall_config->get_name())->dispatch($logout_event);
        $token_storage->set_token(null);
        return $logout_event->get_response();
    }
    private function get_authenticator(?string $authenticator_name, string $firewall_name): Authenticator_Interface
    {
        if (!isset($this->authenticators[$firewall_name])) {
            throw new LogicException(\sprintf('No authenticators found for firewall "%s".', $firewall_name));
        }
        /** @var ServiceProviderInterface $firewallAuthenticatorLocator */
        $firewall_authenticator_locator = $this->authenticators[$firewall_name];
        if (!$authenticator_name) {
            $authenticator_ids = array_filter(array_keys($firewall_authenticator_locator->get_provided_services()), static fn(string $authenticator_id): bool => $authenticator_id !== \sprintf('security.authenticator.remember_me.%s', $firewall_name));
            if (!$authenticator_ids) {
                throw new LogicException(\sprintf('No authenticator was found for the firewall "%s".', $firewall_name));
            }
            if (1 < \count($authenticator_ids)) {
                throw new LogicException(\sprintf('Too many authenticators were found for the current firewall "%s". You must provide an instance of "%s" to login programmatically. The available authenticators for the firewall "%s" are "%s".', $firewall_name, Authenticator_Interface::class, $firewall_name, implode('" ,"', $authenticator_ids)));
            }
            return $firewall_authenticator_locator->get($authenticator_ids[0]);
        }
        if ($firewall_authenticator_locator->has($authenticator_name)) {
            return $firewall_authenticator_locator->get($authenticator_name);
        }
        $authenticator_id = 'security.authenticator.' . $authenticator_name . '.' . $firewall_name;
        if (!$firewall_authenticator_locator->has($authenticator_id)) {
            throw new LogicException(\sprintf('Unable to find an authenticator named "%s" for the firewall "%s". Available authenticators: "%s".', $authenticator_name, $firewall_name, implode('", "', array_keys($firewall_authenticator_locator->get_provided_services()))));
        }
        return $firewall_authenticator_locator->get($authenticator_id);
    }
}