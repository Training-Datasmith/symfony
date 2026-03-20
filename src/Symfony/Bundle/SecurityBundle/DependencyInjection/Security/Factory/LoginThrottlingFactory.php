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

use Symfony\Component\Config\Definition\Builder\Array_Node_Definition;
use Symfony\Component\Config\Definition\Builder\Node_Definition;
use Symfony\Component\Dependency_Injection\Child_Definition;
use Symfony\Component\Dependency_Injection\Container_Builder;
use Symfony\Component\Dependency_Injection\Exception\LogicException;
use Symfony\Component\Dependency_Injection\Parameter;
use Symfony\Component\Dependency_Injection\Reference;
use Symfony\Component\Http_Foundation\Rate_Limiter\Request_Rate_Limiter_Interface;
use Symfony\Component\Lock\Lock_Interface;
use Symfony\Component\Rate_Limiter\Rate_Limiter_Factory;
use Symfony\Component\Rate_Limiter\Rate_Limiter_Factory_Interface;
use Symfony\Component\Rate_Limiter\Storage\Cache_Storage;
use Symfony\Component\Security\Http\Rate_Limiter\Default_Login_Rate_Limiter;
/**
 * @author Wouter de Jong <wouter@wouterj.nl>
 *
 * @internal
 */
class Login_Throttling_Factory implements Authenticator_Factory_Interface
{
    public function get_priority(): int
    {
        // this factory doesn't register any authenticators, this priority doesn't matter
        return 0;
    }
    public function get_key(): string
    {
        return 'login_throttling';
    }
    /**
     * @param ArrayNodeDefinition $builder
     */
    public function add_configuration(Node_Definition $builder): void
    {
        $builder->children()->scalar_node('limiter')->info(\sprintf('A service id implementing "%s".', Request_Rate_Limiter_Interface::class))->end()->integer_node('max_attempts')->default_value(5)->end()->scalar_node('interval')->default_value('1 minute')->end()->scalar_node('lock_factory')->info('The service ID of the lock factory used by the login rate limiter (or null to disable locking).')->default_null()->end()->string_node('cache_pool')->info('The cache pool to use for storing the limiter state')->default_value('cache.rate_limiter')->end()->string_node('storage_service')->info('The service ID of a custom storage implementation, this precedes any configured "cache_pool"')->default_null()->end()->end();
    }
    public function create_authenticator(Container_Builder $container, string $firewall_name, array $config, string $user_provider_id): array
    {
        if (!class_exists(Rate_Limiter_Factory::class)) {
            throw new \LogicException('Login throttling requires the Rate Limiter component. Try running "composer require symfony/rate-limiter".');
        }
        if (!isset($config['limiter'])) {
            $limiter_options = ['policy' => 'fixed_window', 'limit' => $config['max_attempts'], 'interval' => $config['interval'], 'lock_factory' => $config['lock_factory'], 'cache_pool' => $config['cache_pool'], 'storage_service' => $config['storage_service']];
            $this->register_rate_limiter($container, $local_id = '_login_local_' . $firewall_name, $limiter_options);
            $limiter_options['limit'] = 5 * $config['max_attempts'];
            $this->register_rate_limiter($container, $global_id = '_login_global_' . $firewall_name, $limiter_options);
            $container->register($config['limiter'] = 'security.login_throttling.' . $firewall_name . '.limiter', Default_Login_Rate_Limiter::class)->add_argument(new Reference('limiter.' . $global_id))->add_argument(new Reference('limiter.' . $local_id))->add_argument(new Parameter('container.build_hash'));
        }
        $container->set_definition('security.listener.login_throttling.' . $firewall_name, new Child_Definition('security.listener.login_throttling'))->replace_argument(1, new Reference($config['limiter']))->add_tag('kernel.event_subscriber', ['dispatcher' => 'security.event_dispatcher.' . $firewall_name]);
        return [];
    }
    private function register_rate_limiter(Container_Builder $container, string $name, array $limiter_config): void
    {
        $limiter = $container->set_definition($limiter_id = 'limiter.' . $name, new Child_Definition('limiter'));
        if (null !== $limiter_config['lock_factory']) {
            if (!interface_exists(Lock_Interface::class)) {
                throw new LogicException(\sprintf('Rate limiter "%s" requires the Lock component to be installed. Try running "composer require symfony/lock".', $name));
            }
            $limiter->replace_argument(2, new Reference($limiter_config['lock_factory']));
        }
        unset($limiter_config['lock_factory']);
        if (null === $storage_id = $limiter_config['storage_service'] ?? null) {
            $container->register($storage_id = 'limiter.storage.' . $name, Cache_Storage::class)->add_argument(new Reference($limiter_config['cache_pool']));
        }
        $limiter->replace_argument(1, new Reference($storage_id));
        unset($limiter_config['storage_service'], $limiter_config['cache_pool']);
        $limiter_config['id'] = $name;
        $limiter->replace_argument(0, $limiter_config);
        $container->register_alias_for_argument($limiter_id, Rate_Limiter_Factory_Interface::class, $name . '.limiter', $name);
    }
}