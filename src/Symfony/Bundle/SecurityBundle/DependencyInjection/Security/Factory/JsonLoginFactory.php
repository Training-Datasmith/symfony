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

use Symfony\Component\Dependency_Injection\Child_Definition;
use Symfony\Component\Dependency_Injection\Container_Builder;
use Symfony\Component\Dependency_Injection\Reference;
/**
 * JsonLoginFactory creates services for JSON login authentication.
 *
 * @author Kévin Dunglas <dunglas@gmail.com>
 *
 * @internal
 */
class Json_Login_Factory extends Abstract_Factory
{
    public const PRIORITY = -40;
    public function __construct()
    {
        $this->add_option('username_path', 'username');
        $this->add_option('password_path', 'password');
        $this->default_failure_handler_options = [];
        $this->default_success_handler_options = [];
    }
    public function get_priority(): int
    {
        return self::PRIORITY;
    }
    public function get_key(): string
    {
        return 'json-login';
    }
    public function create_authenticator(Container_Builder $container, string $firewall_name, array $config, string $user_provider_id): string
    {
        $authenticator_id = 'security.authenticator.json_login.' . $firewall_name;
        $options = array_intersect_key($config, $this->options);
        $container->set_definition($authenticator_id, new Child_Definition('security.authenticator.json_login'))->replace_argument(1, new Reference($user_provider_id))->replace_argument(2, isset($config['success_handler']) ? new Reference($this->create_authentication_success_handler($container, $firewall_name, $config)) : null)->replace_argument(3, isset($config['failure_handler']) ? new Reference($this->create_authentication_failure_handler($container, $firewall_name, $config)) : null)->replace_argument(4, $options);
        return $authenticator_id;
    }
}