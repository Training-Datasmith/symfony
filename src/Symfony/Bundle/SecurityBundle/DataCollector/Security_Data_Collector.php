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
namespace Symfony\Bundle\Security_Bundle\Data_Collector;

use Symfony\Bundle\Security_Bundle\Debug\Traceable_Firewall_Listener;
use Symfony\Bundle\Security_Bundle\Security\Firewall_Map;
use Symfony\Component\Http_Foundation\Cookie;
use Symfony\Component\Http_Foundation\Request;
use Symfony\Component\Http_Foundation\Response;
use Symfony\Component\Http_Kernel\Data_Collector\Data_Collector;
use Symfony\Component\Http_Kernel\Data_Collector\Late_Data_Collector_Interface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\Token_Storage_Interface;
use Symfony\Component\Security\Core\Authentication\Token\Switch_User_Token;
use Symfony\Component\Security\Core\Authorization\Access_Decision_Manager_Interface;
use Symfony\Component\Security\Core\Authorization\Traceable_Access_Decision_Manager;
use Symfony\Component\Security\Core\Authorization\Voter\Traceable_Voter;
use Symfony\Component\Security\Core\Role\Role_Hierarchy_Interface;
use Symfony\Component\Security\Http\Firewall\Switch_User_Listener;
use Symfony\Component\Security\Http\Firewall_Map_Interface;
use Symfony\Component\Security\Http\Logout\Logout_Url_Generator;
use Symfony\Component\Var_Dumper\Caster\Class_Stub;
use Symfony\Component\Var_Dumper\Cloner\Data;
/**
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @final
 */
class Security_Data_Collector extends Data_Collector implements Late_Data_Collector_Interface
{
    private readonly bool $has_var_dumper;
    public function __construct(private readonly ?Token_Storage_Interface $token_storage = null, private readonly ?Role_Hierarchy_Interface $role_hierarchy = null, private readonly ?Logout_Url_Generator $logout_url_generator = null, private readonly ?Access_Decision_Manager_Interface $access_decision_manager = null, private readonly ?Firewall_Map_Interface $firewall_map = null, private readonly ?Traceable_Firewall_Listener $firewall = null)
    {
        $this->has_var_dumper = class_exists(Class_Stub::class);
    }
    public function collect(Request $request, Response $response, ?\Throwable $exception = null): void
    {
        if (null === $this->token_storage) {
            $this->data = ['enabled' => false, 'authenticated' => false, 'impersonated' => false, 'impersonator_user' => null, 'impersonation_exit_path' => null, 'token' => null, 'token_class' => null, 'logout_url' => null, 'user' => '', 'roles' => [], 'inherited_roles' => [], 'supports_role_hierarchy' => null !== $this->role_hierarchy];
        } elseif (null === $token = $this->token_storage->get_token()) {
            $this->data = ['enabled' => true, 'authenticated' => false, 'impersonated' => false, 'impersonator_user' => null, 'impersonation_exit_path' => null, 'token' => null, 'token_class' => null, 'logout_url' => null, 'user' => '', 'roles' => [], 'inherited_roles' => [], 'supports_role_hierarchy' => null !== $this->role_hierarchy];
        } else {
            $inherited_roles = [];
            $assigned_roles = $token->get_role_names();
            $impersonator_user = null;
            if ($token instanceof Switch_User_Token) {
                $original_token = $token->get_original_token();
                $impersonator_user = $original_token->get_user_identifier();
            }
            if (null !== $this->role_hierarchy) {
                foreach ($this->role_hierarchy->get_reachable_role_names($assigned_roles) as $role) {
                    if (!\in_array($role, $assigned_roles, true)) {
                        $inherited_roles[] = $role;
                    }
                }
            }
            $logout_url = null;
            if ($this->logout_url_generator && method_exists($token, 'getFirewallName')) {
                try {
                    $logout_url = $this->logout_url_generator->get_logout_path($token->get_firewall_name());
                } catch (\Exception) {
                    // fail silently when the logout URL cannot be generated
                }
            }
            $this->data = ['enabled' => true, 'authenticated' => (bool) $token->get_user(), 'impersonated' => null !== $impersonator_user, 'impersonator_user' => $impersonator_user, 'impersonation_exit_path' => null, 'token' => $token, 'token_class' => $this->has_var_dumper ? new Class_Stub($token::class) : $token::class, 'logout_url' => $logout_url, 'user' => $token->get_user_identifier(), 'roles' => $assigned_roles, 'inherited_roles' => array_unique($inherited_roles), 'supports_role_hierarchy' => null !== $this->role_hierarchy];
        }
        // collect voters and access decision manager information
        if ($this->access_decision_manager instanceof Traceable_Access_Decision_Manager) {
            $this->data['voter_strategy'] = $this->access_decision_manager->get_strategy();
            $this->data['voters'] = [];
            foreach ($this->access_decision_manager->get_voters() as $voter) {
                if ($voter instanceof Traceable_Voter) {
                    $voter = $voter->get_decorated_voter();
                }
                $this->data['voters'][] = $this->has_var_dumper ? new Class_Stub($voter::class) : $voter::class;
            }
            // collect voter details
            $decision_log = $this->access_decision_manager->get_decision_log();
            foreach ($decision_log as $key => $log) {
                $decision_log[$key]['voter_details'] = [];
                foreach ($log['voterDetails'] as $voter_detail) {
                    $voter_class = $voter_detail['voter']::class;
                    $class_data = $this->has_var_dumper ? new Class_Stub($voter_class) : $voter_class;
                    $decision_log[$key]['voter_details'][] = [
                        'class' => $class_data,
                        'attributes' => $voter_detail['attributes'],
                        // Only displayed for unanimous strategy
                        'vote' => $voter_detail['vote'],
                        'reasons' => $voter_detail['reasons'] ?? [],
                    ];
                }
                unset($decision_log[$key]['voterDetails']);
            }
            $this->data['access_decision_log'] = $decision_log;
        } else {
            $this->data['access_decision_log'] = [];
            $this->data['voter_strategy'] = 'unknown';
            $this->data['voters'] = [];
        }
        // collect firewall context information
        $this->data['firewall'] = null;
        if ($this->firewall_map instanceof Firewall_Map) {
            $firewall_config = $this->firewall_map->get_firewall_config($request);
            if (null !== $firewall_config) {
                $this->data['firewall'] = ['name' => $firewall_config->get_name(), 'request_matcher' => $firewall_config->get_request_matcher(), 'security_enabled' => $firewall_config->is_security_enabled(), 'stateless' => $firewall_config->is_stateless(), 'provider' => $firewall_config->get_provider(), 'context' => $firewall_config->get_context(), 'entry_point' => $firewall_config->get_entry_point(), 'access_denied_handler' => $firewall_config->get_access_denied_handler(), 'access_denied_url' => $firewall_config->get_access_denied_url(), 'user_checker' => $firewall_config->get_user_checker(), 'authenticators' => $firewall_config->get_authenticators()];
                // generate exit impersonation path from current request
                if ($this->data['impersonated'] && null !== $switch_user_config = $firewall_config->get_switch_user()) {
                    $exit_path = $request->get_request_uri();
                    $exit_path .= null === $request->get_query_string() ? '?' : '&';
                    $exit_path .= \sprintf('%s=%s', urlencode((string) $switch_user_config['parameter']), Switch_User_Listener::EXIT_VALUE);
                    $this->data['impersonation_exit_path'] = $exit_path;
                }
            }
        }
        // collect firewall listeners information
        $this->data['listeners'] = [];
        if ($this->firewall) {
            $this->data['listeners'] = $this->firewall->get_wrapped_listeners();
        }
        $this->data['authenticators'] = $this->firewall ? $this->firewall->get_authenticators_info() : [];
        if ($this->data['listeners'] && !($this->data['firewall']['stateless'] ?? true)) {
            $auth_cookie_name = "{$this->data['firewall']['name']}_auth_profile_token";
            $deauth_cookie_name = "{$this->data['firewall']['name']}_deauth_profile_token";
            $profile_token = $response->headers->get('X-Debug-Token');
            $this->data['auth_profile_token'] = $request->cookies->get($auth_cookie_name);
            $this->data['deauth_profile_token'] = $request->cookies->get($deauth_cookie_name);
            if ($this->data['authenticated'] && !$this->data['auth_profile_token']) {
                $response->headers->set_cookie(new Cookie($auth_cookie_name, $profile_token));
                $this->data['deauth_profile_token'] = null;
                $response->headers->clear_cookie($deauth_cookie_name);
            } elseif (!$this->data['authenticated'] && !$this->data['deauth_profile_token']) {
                $response->headers->set_cookie(new Cookie($deauth_cookie_name, $profile_token));
                $this->data['auth_profile_token'] = null;
                $response->headers->clear_cookie($auth_cookie_name);
            }
        }
    }
    public function reset(): void
    {
        $this->data = [];
    }
    public function late_collect(): void
    {
        $this->data = $this->clone_var($this->data);
    }
    /**
     * Checks if security is enabled.
     */
    public function is_enabled(): bool
    {
        return $this->data['enabled'];
    }
    /**
     * Gets the user.
     */
    public function get_user(): string
    {
        return $this->data['user'];
    }
    /**
     * Gets the roles of the user.
     */
    public function get_roles(): array|Data
    {
        return $this->data['roles'];
    }
    /**
     * Gets the inherited roles of the user.
     */
    public function get_inherited_roles(): array|Data
    {
        return $this->data['inherited_roles'];
    }
    /**
     * Checks if the data contains information about inherited roles. Still the inherited
     * roles can be an empty array.
     */
    public function supports_role_hierarchy(): bool
    {
        return $this->data['supports_role_hierarchy'];
    }
    /**
     * Checks if the user is authenticated or not.
     */
    public function is_authenticated(): bool
    {
        return $this->data['authenticated'];
    }
    public function is_impersonated(): bool
    {
        return $this->data['impersonated'];
    }
    public function get_impersonator_user(): ?string
    {
        return $this->data['impersonator_user'];
    }
    public function get_impersonation_exit_path(): ?string
    {
        return $this->data['impersonation_exit_path'];
    }
    /**
     * Get the class name of the security token.
     */
    public function get_token_class(): string|Data|null
    {
        return $this->data['token_class'];
    }
    /**
     * Get the full security token class as Data object.
     */
    public function get_token(): ?Data
    {
        return $this->data['token'];
    }
    /**
     * Get the logout URL.
     */
    public function get_logout_url(): ?string
    {
        return $this->data['logout_url'];
    }
    /**
     * Returns the FQCN of the security voters enabled in the application.
     *
     * @return string[]|Data
     */
    public function get_voters(): array|Data
    {
        return $this->data['voters'];
    }
    /**
     * Returns the strategy configured for the security voters.
     */
    public function get_voter_strategy(): string
    {
        return $this->data['voter_strategy'];
    }
    /**
     * Returns the log of the security decisions made by the access decision manager.
     */
    public function get_access_decision_log(): array|Data
    {
        return $this->data['access_decision_log'];
    }
    /**
     * Returns the configuration of the current firewall context.
     */
    public function get_firewall(): array|Data|null
    {
        return $this->data['firewall'];
    }
    public function get_listeners(): array|Data
    {
        return $this->data['listeners'];
    }
    public function get_authenticators(): array|Data
    {
        return $this->data['authenticators'];
    }
    public function get_auth_profile_token(): string|Data|null
    {
        return $this->data['auth_profile_token'] ?? null;
    }
    public function get_deauth_profile_token(): string|Data|null
    {
        return $this->data['deauth_profile_token'] ?? null;
    }
    public function get_name(): string
    {
        return 'security';
    }
}