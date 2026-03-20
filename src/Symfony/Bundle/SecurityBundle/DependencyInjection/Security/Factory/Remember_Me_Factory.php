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
namespace Symfony\Bundle\Security_Bundle\Dependency_Injection\Security\Factory;

use Symfony\Bridge\Doctrine\Security\Remember_Me\Doctrine_Token_Provider;
use Symfony\Bundle\Security_Bundle\Remember_Me\Decorated_Remember_Me_Handler;
use Symfony\Component\Config\Definition\Builder\Node_Definition;
use Symfony\Component\Config\Definition\Exception\Invalid_Configuration_Exception;
use Symfony\Component\Config\File_Locator;
use Symfony\Component\Dependency_Injection\Child_Definition;
use Symfony\Component\Dependency_Injection\Container_Builder;
use Symfony\Component\Dependency_Injection\Container_Interface;
use Symfony\Component\Dependency_Injection\Extension\Prepend_Extension_Interface;
use Symfony\Component\Dependency_Injection\Loader\Php_File_Loader;
use Symfony\Component\Dependency_Injection\Reference;
use Symfony\Component\Http_Foundation\Cookie;
use Symfony\Component\Security\Core\Authentication\Remember_Me\Cache_Token_Verifier;
/**
 * @internal
 */
class Remember_Me_Factory implements Authenticator_Factory_Interface, Prepend_Extension_Interface
{
    public const PRIORITY = -50;
    protected array $options = ['name' => 'REMEMBERME', 'lifetime' => 31536000, 'path' => '/', 'domain' => null, 'secure' => false, 'httponly' => true, 'samesite' => null, 'always_remember_me' => false, 'remember_me_parameter' => '_remember_me'];
    public function create_authenticator(Container_Builder $container, string $firewall_name, array $config, string $user_provider_id): string
    {
        if (!$container->has_definition('security.authenticator.remember_me')) {
            $loader = new Php_File_Loader($container, new File_Locator(\dirname(__DIR__) . '/../../Resources/config'));
            $loader->load('security_authenticator_remember_me.php');
        }
        if ('auto' === $config['secure']) {
            $config['secure'] = null;
        }
        // create remember me handler (which manage the remember-me cookies)
        $remember_me_handler_id = 'security.authenticator.remember_me_handler.' . $firewall_name;
        if (isset($config['service']) && isset($config['token_provider'])) {
            throw new Invalid_Configuration_Exception(\sprintf('You cannot use both "service" and "token_provider" in "security.firewalls.%s.remember_me".', $firewall_name));
        }
        if (isset($config['service'])) {
            $container->register($remember_me_handler_id, Decorated_Remember_Me_Handler::class)->add_argument(new Reference($config['service']))->add_tag('security.remember_me_handler', ['firewall' => $firewall_name]);
        } elseif (isset($config['token_provider'])) {
            $token_provider_id = $this->create_token_provider($container, $firewall_name, $config['token_provider']);
            $token_verifier = $this->create_token_verifier($container, $firewall_name, $config['token_verifier'] ?? null);
            $container->set_definition($remember_me_handler_id, new Child_Definition('security.authenticator.persistent_remember_me_handler'))->replace_argument(0, new Reference($token_provider_id))->replace_argument(1, new Reference($user_provider_id))->replace_argument(3, $config)->replace_argument(5, $token_verifier)->add_tag('security.remember_me_handler', ['firewall' => $firewall_name]);
        } else {
            $signature_hasher_id = 'security.authenticator.remember_me_signature_hasher.' . $firewall_name;
            $container->set_definition($signature_hasher_id, new Child_Definition('security.authenticator.remember_me_signature_hasher'))->replace_argument(1, $config['signature_properties'])->replace_argument(2, $config['secret']);
            $container->set_definition($remember_me_handler_id, new Child_Definition('security.authenticator.signature_remember_me_handler'))->replace_argument(0, new Reference($signature_hasher_id))->replace_argument(1, new Reference($user_provider_id))->replace_argument(3, $config)->add_tag('security.remember_me_handler', ['firewall' => $firewall_name]);
        }
        // create check remember me conditions listener (which checks if a remember-me cookie is supported and requested)
        $remember_me_conditions_listener_id = 'security.listener.check_remember_me_conditions.' . $firewall_name;
        $container->set_definition($remember_me_conditions_listener_id, new Child_Definition('security.listener.check_remember_me_conditions'))->replace_argument(0, array_intersect_key($config, ['always_remember_me' => true, 'remember_me_parameter' => true]))->add_tag('kernel.event_subscriber', ['dispatcher' => 'security.event_dispatcher.' . $firewall_name]);
        // create remember me listener (which executes the remember me services for other authenticators and logout)
        $remember_me_listener_id = 'security.listener.remember_me.' . $firewall_name;
        $container->set_definition($remember_me_listener_id, new Child_Definition('security.listener.remember_me'))->replace_argument(0, new Reference($remember_me_handler_id))->add_tag('kernel.event_subscriber', ['dispatcher' => 'security.event_dispatcher.' . $firewall_name]);
        // create remember me authenticator (which re-authenticates the user based on the remember-me cookie)
        $authenticator_id = 'security.authenticator.remember_me.' . $firewall_name;
        $container->set_definition($authenticator_id, new Child_Definition('security.authenticator.remember_me'))->replace_argument(0, new Reference($remember_me_handler_id))->replace_argument(2, $config['name'] ?? $this->options['name']);
        return $authenticator_id;
    }
    public function get_priority(): int
    {
        return self::PRIORITY;
    }
    public function get_key(): string
    {
        return 'remember-me';
    }
    public function add_configuration(Node_Definition $node): void
    {
        $builder = $node->children();
        $builder->scalar_node('secret')->cannot_be_empty()->default_value('%kernel.secret%')->end()->scalar_node('service')->end()->array_node('user_providers', 'user_provider')->accept_and_wrap(['string'])->prototype('scalar')->end()->end()->boolean_node('catch_exceptions')->default_true()->end()->array_node('signature_properties', 'signature_property')->prototype('scalar')->end()->requires_at_least_one_element()->info('An array of properties on your User that are used to sign the remember-me cookie. If any of these change, all existing cookies will become invalid.')->example(['email', 'password'])->default_value(['password'])->end()->array_node('token_provider')->accept_and_wrap(['string'], 'service')->children()->scalar_node('service')->info('The service ID of a custom remember-me token provider.')->end()->array_node('doctrine')->can_be_enabled()->children()->scalar_node('connection')->default_null()->end()->end()->end()->end()->end()->scalar_node('token_verifier')->info('The service ID of a custom rememberme token verifier.')->end();
        foreach ($this->options as $name => $value) {
            if ('secure' === $name) {
                $builder->enum_node($name)->values([true, false, 'auto'])->default_value('auto' === $value ? null : $value);
            } elseif ('samesite' === $name) {
                $builder->enum_node($name)->values([null, Cookie::SAMESITE_LAX, Cookie::SAMESITE_STRICT, Cookie::SAMESITE_NONE])->default_value($value);
            } elseif (\is_bool($value)) {
                $builder->boolean_node($name)->default_value($value);
            } elseif (\is_int($value)) {
                $builder->integer_node($name)->default_value($value);
            } else {
                $builder->scalar_node($name)->default_value($value);
            }
        }
    }
    private function create_token_provider(Container_Builder $container, string $firewall_name, array $config): string
    {
        $token_provider_id = $config['service'] ?? false;
        if ($config['doctrine']['enabled'] ?? false) {
            if (!class_exists(Doctrine_Token_Provider::class)) {
                throw new Invalid_Configuration_Exception('Cannot use the "doctrine" token provider for "remember_me" because the Doctrine Bridge is not installed. Try running "composer require symfony/doctrine-bridge".');
            }
            if (null === $config['doctrine']['connection']) {
                $connection_id = 'database_connection';
            } else {
                $connection_id = 'doctrine.dbal.' . $config['doctrine']['connection'] . '_connection';
            }
            $token_provider_id = 'security.remember_me.doctrine_token_provider.' . $firewall_name;
            $container->register($token_provider_id, Doctrine_Token_Provider::class)->add_argument(new Reference($connection_id));
        }
        if (!$token_provider_id) {
            throw new Invalid_Configuration_Exception(\sprintf('No token provider was set for firewall "%s". Either configure a service ID or set "remember_me.token_provider.doctrine" to true.', $firewall_name));
        }
        return $token_provider_id;
    }
    private function create_token_verifier(Container_Builder $container, string $firewall_name, ?string $service_id): Reference
    {
        if ($service_id) {
            return new Reference($service_id);
        }
        $token_verifier_id = 'security.remember_me.token_verifier.' . $firewall_name;
        $container->register($token_verifier_id, Cache_Token_Verifier::class)->add_argument(new Reference('cache.security_token_verifier', Container_Interface::NULL_ON_INVALID_REFERENCE))->add_argument(60)->add_argument('rememberme-' . $firewall_name . '-stale-');
        return new Reference($token_verifier_id, Container_Interface::NULL_ON_INVALID_REFERENCE);
    }
    public function prepend(Container_Builder $container): void
    {
        $remember_me_secure_default = false;
        $remember_me_same_site_default = null;
        if (!isset($container->get_extensions()['framework'])) {
            return;
        }
        foreach ($container->get_extension_config('framework') as $config) {
            if (isset($config['session']) && \is_array($config['session'])) {
                $remember_me_secure_default = $config['session']['cookie_secure'] ?? $remember_me_secure_default;
                $remember_me_same_site_default = \array_key_exists('cookie_samesite', $config['session']) ? $config['session']['cookie_samesite'] : $remember_me_same_site_default;
            }
        }
        $this->options['secure'] = $remember_me_secure_default;
        $this->options['samesite'] = $remember_me_same_site_default;
    }
}