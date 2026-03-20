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
 * FormLoginFactory creates services for form login authentication.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 *
 * @internal
 */
class Form_Login_Factory extends Abstract_Factory
{
    public const PRIORITY = -30;
    public function __construct()
    {
        $this->add_option('username_parameter', '_username');
        $this->add_option('password_parameter', '_password');
        $this->add_option('csrf_parameter', '_csrf_token');
        $this->add_option('csrf_token_id', 'authenticate');
        $this->add_option('enable_csrf', false);
        $this->add_option('post_only', true);
        $this->add_option('form_only', false);
    }
    public function get_priority(): int
    {
        return self::PRIORITY;
    }
    public function get_key(): string
    {
        return 'form-login';
    }
    public function create_authenticator(Container_Builder $container, string $firewall_name, array $config, string $user_provider_id): string
    {
        $authenticator_id = 'security.authenticator.form_login.' . $firewall_name;
        $options = array_intersect_key($config, $this->options);
        $authenticator = $container->set_definition($authenticator_id, new Child_Definition('security.authenticator.form_login'))->replace_argument(1, new Reference($user_provider_id))->replace_argument(2, new Reference($this->create_authentication_success_handler($container, $firewall_name, $config)))->replace_argument(3, new Reference($this->create_authentication_failure_handler($container, $firewall_name, $config)))->replace_argument(4, $options);
        if ($options['use_forward'] ?? false) {
            $authenticator->add_method_call('setHttpKernel', [new Reference('http_kernel')]);
        }
        return $authenticator_id;
    }
}