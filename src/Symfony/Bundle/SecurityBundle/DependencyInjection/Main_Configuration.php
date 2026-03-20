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
namespace Symfony\Bundle\Security_Bundle\Dependency_Injection;

use Symfony\Bundle\Security_Bundle\Dependency_Injection\Security\Factory\Abstract_Factory;
use Symfony\Bundle\Security_Bundle\Dependency_Injection\Security\Factory\Authenticator_Factory_Interface;
use Symfony\Component\Config\Definition\Builder\Array_Node_Definition;
use Symfony\Component\Config\Definition\Builder\Tree_Builder;
use Symfony\Component\Config\Definition\Configuration_Interface;
use Symfony\Component\Config\Definition\Exception\Invalid_Configuration_Exception;
use Symfony\Component\Security\Http\Authentication\Expose_Security_Level;
use Symfony\Component\Security\Http\Entry_Point\Authentication_Entry_Point_Interface;
use Symfony\Component\Security\Http\Session\Session_Authentication_Strategy;
/**
 * SecurityExtension configuration structure.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class Main_Configuration implements Configuration_Interface
{
    /** @internal */
    public const STRATEGY_AFFIRMATIVE = 'affirmative';
    /** @internal */
    public const STRATEGY_CONSENSUS = 'consensus';
    /** @internal */
    public const STRATEGY_UNANIMOUS = 'unanimous';
    /** @internal */
    public const STRATEGY_PRIORITY = 'priority';
    /**
     * @param array<AuthenticatorFactoryInterface> $factories
     */
    public function __construct(private readonly array $factories, private readonly array $user_provider_factories)
    {
    }
    /**
     * Generates the configuration tree builder.
     */
    public function get_config_tree_builder(): Tree_Builder
    {
        $tb = new Tree_Builder('security');
        $root_node = $tb->get_root_node();
        $root_node->doc_url('https://symfony.com/doc/{version:major}.{version:minor}/reference/configuration/security.html', 'symfony/security-bundle')->children()->scalar_node('access_denied_url')->default_null()->example('/foo/error403')->end()->enum_node('session_fixation_strategy')->values([Session_Authentication_Strategy::NONE, Session_Authentication_Strategy::MIGRATE, Session_Authentication_Strategy::INVALIDATE])->default_value(Session_Authentication_Strategy::MIGRATE)->end()->enum_node('expose_security_errors')->before_normalization()->if_string()->then(static fn($v) => Expose_Security_Level::try_from($v))->end()->values(Expose_Security_Level::cases())->default_value(Expose_Security_Level::None)->end()->boolean_node('erase_credentials')->default_true()->end()->array_node('access_decision_manager')->add_defaults_if_not_set()->children()->enum_node('strategy')->values($this->get_access_decision_strategies())->end()->scalar_node('service')->end()->scalar_node('strategy_service')->end()->boolean_node('allow_if_all_abstain')->default_false()->end()->boolean_node('allow_if_equal_granted_denied')->default_true()->end()->end()->validate()->if_true(static fn($v): bool => isset($v['strategy'], $v['service']))->then_invalid('"strategy" and "service" cannot be used together.')->end()->validate()->if_true(static fn($v): bool => isset($v['strategy'], $v['strategy_service']))->then_invalid('"strategy" and "strategy_service" cannot be used together.')->end()->validate()->if_true(static fn($v): bool => isset($v['service'], $v['strategy_service']))->then_invalid('"service" and "strategy_service" cannot be used together.')->end()->end()->end();
        $this->add_password_hashers_section($root_node);
        $this->add_providers_section($root_node);
        $this->add_firewalls_section($root_node, $this->factories);
        $this->add_access_control_section($root_node);
        $this->add_role_hierarchy_section($root_node);
        return $tb;
    }
    private function add_role_hierarchy_section(Array_Node_Definition $root_node): void
    {
        $root_node->children()->array_node('role_hierarchy', 'role')->use_attribute_as_key('id')->prototype('array')->perform_no_deep_merging()->before_normalization()->if_string()->then(static fn($v) => preg_split('/\s*,\s*/', (string) $v))->end()->prototype('scalar')->end()->end()->end()->end();
    }
    private function add_access_control_section(Array_Node_Definition $root_node): void
    {
        $root_node->children()->array_node('access_control', 'rule')->cannot_be_overwritten()->prototype('array')->children()->scalar_node('request_matcher')->default_null()->end()->scalar_node('requires_channel')->default_null()->end()->scalar_node('path')->default_null()->info('Use the urldecoded format.')->example('^/path to resource/')->end()->scalar_node('host')->default_null()->end()->integer_node('port')->default_null()->end()->array_node('ips', 'ip')->accept_and_wrap(['string'])->prototype('scalar')->end()->end()->array_node('attributes', 'attribute')->use_attribute_as_key('key')->prototype('scalar')->end()->end()->scalar_node('route')->default_null()->end()->array_node('methods', 'method')->before_normalization()->if_string()->then(static fn($v) => preg_split('/\s*,\s*/', (string) $v))->end()->prototype('scalar')->end()->end()->scalar_node('allow_if')->default_null()->end()->end()->children()->array_node('roles', 'role')->before_normalization()->if_string()->then(static fn($v) => preg_split('/\s*,\s*/', (string) $v))->end()->prototype('scalar')->end()->end()->end()->end()->end()->end();
    }
    /**
     * @param array<AuthenticatorFactoryInterface> $factories
     */
    private function add_firewalls_section(Array_Node_Definition $root_node, array $factories): void
    {
        $firewall_node_builder = $root_node->children()->array_node('firewalls', 'firewall')->is_required()->requires_at_least_one_element()->disallow_new_keys_in_subsequent_configs()->use_attribute_as_key('name')->prototype('array')->children();
        $firewall_node_builder->scalar_node('pattern')->before_normalization()->if_array()->then(static fn($v): string => \sprintf('(?:%s)', implode('|', $v)))->end()->end()->scalar_node('host')->end()->array_node('methods')->before_normalization()->if_string()->then(static fn($v) => preg_split('/\s*,\s*/', (string) $v))->end()->prototype('scalar')->end()->end()->boolean_node('security')->default_true()->end()->scalar_node('user_checker')->default_value('security.user_checker')->treat_null_like('security.user_checker')->info('The UserChecker to use when authenticating users in this firewall.')->end()->scalar_node('request_matcher')->end()->scalar_node('access_denied_url')->end()->scalar_node('access_denied_handler')->end()->scalar_node('entry_point')->info(\sprintf('An enabled authenticator name or a service id that implements "%s".', Authentication_Entry_Point_Interface::class))->end()->scalar_node('provider')->end()->boolean_node('stateless')->default_false()->end()->boolean_node('lazy')->default_false()->end()->scalar_node('context')->cannot_be_empty()->end()->array_node('logout')->treat_true_like([])->can_be_unset()->before_normalization()->if_array()->then(static function (array $v): array {
            if (isset($v['csrf_token_manager'])) {
                $v['enable_csrf'] ??= true;
            } elseif ($v['enable_csrf'] ?? false) {
                $v['csrf_token_manager'] = 'security.csrf.token_manager';
            }
            return $v;
        })->end()->children()->boolean_node('enable_csrf')->default_null()->end()->scalar_node('csrf_token_id')->default_value('logout')->end()->scalar_node('csrf_parameter')->default_value('_csrf_token')->end()->scalar_node('csrf_token_manager')->end()->scalar_node('path')->default_value('/logout')->end()->scalar_node('target')->default_value('/')->end()->boolean_node('invalidate_session')->default_true()->end()->array_node('clear_site_data')->perform_no_deep_merging()->before_normalization()->if_string()->then(static fn($v): array => $v ? array_map(trim(...), explode(',', (string) $v)) : [])->end()->enum_prototype()->values(['*', 'cache', 'cookies', 'storage', 'clientHints', 'executionContexts', 'prefetchCache', 'prerenderCache'])->end()->end()->end()->children()->array_node('delete_cookies', 'delete_cookie')->normalize_keys(false)->accept_and_wrap(['string'])->before_normalization()->if_array()->then(static fn($v): array => array_map(static fn($v) => \is_string($v) ? ['name' => $v] : $v, $v))->end()->use_attribute_as_key('name')->prototype('array')->children()->scalar_node('path')->default_null()->end()->scalar_node('domain')->default_null()->end()->scalar_node('secure')->default_false()->end()->scalar_node('samesite')->default_null()->end()->scalar_node('partitioned')->default_false()->end()->end()->end()->end()->end()->end()->array_node('switch_user')->can_be_unset()->children()->scalar_node('provider')->end()->scalar_node('parameter')->default_value('_switch_user')->end()->scalar_node('role')->default_value('ROLE_ALLOWED_TO_SWITCH')->end()->scalar_node('target_route')->default_value(null)->end()->end()->end()->array_node('required_badges', 'required_badge')->info('A list of badges that must be present on the authenticated passport.')->validate()->always()->then(static fn($required_badges) => array_map(static function (string $required_badge): string {
            if (class_exists($required_badge)) {
                return $required_badge;
            }
            if (!str_contains($required_badge, '\\')) {
                $fqcn = 'Symfony\Component\Security\Http\Authenticator\Passport\Badge\\' . $required_badge;
                if (class_exists($fqcn)) {
                    return $fqcn;
                }
            }
            throw new Invalid_Configuration_Exception(\sprintf('Undefined security Badge class "%s" set in "security.firewall.required_badges".', $required_badge));
        }, $required_badges))->end()->prototype('scalar')->end()->end();
        $abstract_factory_keys = [];
        foreach ($factories as $factory) {
            $name = str_replace('-', '_', $factory->get_key());
            $factory_node = $firewall_node_builder->array_node($name)->can_be_unset();
            if ($factory instanceof Abstract_Factory) {
                $abstract_factory_keys[] = $name;
            }
            $factory->add_configuration($factory_node);
        }
        // check for unreachable check paths
        $firewall_node_builder->end()->validate()->if_true(static fn($v): bool => true === $v['security'] && isset($v['pattern']) && !isset($v['request_matcher']))->then(static function (array $firewall) use ($abstract_factory_keys): array {
            foreach ($abstract_factory_keys as $k) {
                if (!isset($firewall[$k]['check_path'])) {
                    continue;
                }
                if (str_contains((string) $firewall[$k]['check_path'], '/') && !preg_match('#' . $firewall['pattern'] . '#', (string) $firewall[$k]['check_path'])) {
                    throw new \LogicException(\sprintf('The check_path "%s" for login method "%s" is not matched by the firewall pattern "%s".', $firewall[$k]['check_path'], $k, $firewall['pattern']));
                }
            }
            return $firewall;
        })->end();
    }
    private function add_providers_section(Array_Node_Definition $root_node): void
    {
        $provider_node_builder = $root_node->children()->array_node('providers', 'provider')->example(['my_memory_provider' => ['memory' => ['users' => ['foo' => ['password' => 'foo', 'roles' => 'ROLE_USER'], 'bar' => ['password' => 'bar', 'roles' => '[ROLE_USER, ROLE_ADMIN]']]]], 'my_entity_provider' => ['entity' => ['class' => 'SecurityBundle:User', 'property' => 'username']]])->requires_at_least_one_element()->use_attribute_as_key('name')->prototype('array');
        $provider_node_builder->children()->scalar_node('id')->end()->array_node('chain')->children()->array_node('providers', 'provider')->before_normalization()->if_string()->then(static fn($v) => preg_split('/\s*,\s*/', (string) $v))->end()->prototype('scalar')->end()->end()->end()->end()->end();
        foreach ($this->user_provider_factories as $factory) {
            $name = str_replace('-', '_', $factory->get_key());
            $factory_node = $provider_node_builder->children()->array_node($name)->can_be_unset();
            $factory->add_configuration($factory_node);
        }
        $provider_node_builder->validate()->if_true(static fn($v): bool => \count($v) > 1)->then_invalid('You cannot set multiple provider types for the same provider')->end()->validate()->if_true(static fn($v): bool => 0 === \count($v))->then_invalid('You must set a provider definition for the provider.')->end();
    }
    private function add_password_hashers_section(Array_Node_Definition $root_node): void
    {
        $root_node->children()->array_node('password_hashers', 'password_hasher')->example(['App\Entity\User1' => 'auto', 'App\Entity\User2' => ['algorithm' => 'auto', 'time_cost' => 8, 'cost' => 13]])->requires_at_least_one_element()->use_attribute_as_key('class')->prototype('array')->can_be_unset()->perform_no_deep_merging()->accept_and_wrap(['string'], 'algorithm')->children()->scalar_node('algorithm')->cannot_be_empty()->validate()->if_true(static fn($v): bool => !\is_string($v))->then_invalid('You must provide a string value.')->end()->end()->array_node('migrate_from')->accept_and_wrap(['string'])->prototype('scalar')->end()->end()->scalar_node('hash_algorithm')->info('Name of hashing algorithm for PBKDF2 (i.e. sha256, sha512, etc..) See hash_algos() for a list of supported algorithms.')->default_value('sha512')->end()->scalar_node('key_length')->default_value(40)->end()->boolean_node('ignore_case')->default_false()->end()->boolean_node('encode_as_base64')->default_true()->end()->scalar_node('iterations')->default_value(5000)->end()->integer_node('cost')->min(4)->max(31)->default_null()->end()->scalar_node('memory_cost')->default_null()->end()->scalar_node('time_cost')->default_null()->end()->scalar_node('id')->end()->end()->end()->end()->end();
    }
    private function get_access_decision_strategies(): array
    {
        return [self::STRATEGY_AFFIRMATIVE, self::STRATEGY_CONSENSUS, self::STRATEGY_UNANIMOUS, self::STRATEGY_PRIORITY];
    }
}