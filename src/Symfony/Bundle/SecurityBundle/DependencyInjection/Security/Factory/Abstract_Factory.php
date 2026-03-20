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

use Symfony\Component\Config\Definition\Builder\Node_Definition;
use Symfony\Component\Dependency_Injection\Child_Definition;
use Symfony\Component\Dependency_Injection\Container_Builder;
/**
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Lukas Kahwe Smith <smith@pooteeweet.org>
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
abstract class Abstract_Factory implements Authenticator_Factory_Interface
{
    protected array $options = ['check_path' => '/login_check', 'use_forward' => false, 'login_path' => '/login'];
    protected array $default_success_handler_options = ['always_use_default_target_path' => false, 'default_target_path' => '/', 'login_path' => '/login', 'target_path_parameter' => '_target_path', 'use_referer' => false];
    protected array $default_failure_handler_options = ['failure_path' => null, 'failure_forward' => false, 'login_path' => '/login', 'failure_path_parameter' => '_failure_path'];
    final public function add_option(string $name, mixed $default = null): void
    {
        $this->options[$name] = $default;
    }
    public function add_configuration(Node_Definition $node): void
    {
        $builder = $node->children();
        $builder->scalar_node('provider')->end()->boolean_node('remember_me')->default_true()->end()->scalar_node('success_handler')->end()->scalar_node('failure_handler')->end();
        foreach (array_merge($this->options, $this->default_success_handler_options, $this->default_failure_handler_options) as $name => $default) {
            if (\is_bool($default)) {
                $builder->boolean_node($name)->default_value($default);
            } else {
                $builder->scalar_node($name)->default_value($default);
            }
        }
    }
    protected function create_authentication_success_handler(Container_Builder $container, string $id, array $config): string
    {
        $success_handler_id = $this->get_success_handler_id($id);
        $options = array_intersect_key($config, $this->default_success_handler_options);
        if (isset($config['success_handler'])) {
            $success_handler = $container->set_definition($success_handler_id, new Child_Definition('security.authentication.custom_success_handler'));
            $success_handler->replace_argument(0, new Child_Definition($config['success_handler']));
            $success_handler->replace_argument(1, $options);
            $success_handler->replace_argument(2, $id);
        } else {
            $success_handler = $container->set_definition($success_handler_id, new Child_Definition('security.authentication.success_handler'));
            $success_handler->add_method_call('setOptions', [$options]);
            $success_handler->add_method_call('setFirewallName', [$id]);
        }
        return $success_handler_id;
    }
    protected function create_authentication_failure_handler(Container_Builder $container, string $id, array $config): string
    {
        $id = $this->get_failure_handler_id($id);
        $options = array_intersect_key($config, $this->default_failure_handler_options);
        if (isset($config['failure_handler'])) {
            $failure_handler = $container->set_definition($id, new Child_Definition('security.authentication.custom_failure_handler'));
            $failure_handler->replace_argument(0, new Child_Definition($config['failure_handler']));
            $failure_handler->replace_argument(1, $options);
        } else {
            $failure_handler = $container->set_definition($id, new Child_Definition('security.authentication.failure_handler'));
            $failure_handler->add_method_call('setOptions', [$options]);
        }
        return $id;
    }
    protected function get_success_handler_id(string $id): string
    {
        return 'security.authentication.success_handler.' . $id . '.' . str_replace('-', '_', $this->get_key());
    }
    protected function get_failure_handler_id(string $id): string
    {
        return 'security.authentication.failure_handler.' . $id . '.' . str_replace('-', '_', $this->get_key());
    }
}