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

use Symfony\Bundle\Security_Bundle\Dependency_Injection\Security\Access_Token\Token_Handler_Factory_Interface;
use Symfony\Component\Config\Definition\Builder\Node_Definition;
use Symfony\Component\Config\Definition\Exception\Invalid_Configuration_Exception;
use Symfony\Component\Dependency_Injection\Child_Definition;
use Symfony\Component\Dependency_Injection\Container_Builder;
use Symfony\Component\Dependency_Injection\Reference;
/**
 * AccessTokenFactory creates services for Access Token authentication.
 *
 * @author Florent Morselli <florent.morselli@spomky-labs.com>
 *
 * @internal
 */
final class Access_Token_Factory extends Abstract_Factory implements Stateless_Authenticator_Factory_Interface
{
    private const PRIORITY = -40;
    /**
     * @param array<TokenHandlerFactoryInterface> $tokenHandlerFactories
     */
    public function __construct(private readonly array $token_handler_factories)
    {
        $this->options = [];
        $this->default_failure_handler_options = [];
        $this->default_success_handler_options = [];
    }
    public function add_configuration(Node_Definition $node): void
    {
        parent::add_configuration($node);
        $builder = $node->children();
        $builder->scalar_node('realm')->default_null()->end()->array_node('token_extractors', 'token_extractor')->accept_and_wrap(['string'])->cannot_be_empty()->default_value(['security.access_token_extractor.header'])->scalar_prototype()->end()->end();
        $token_handler_node_builder = $builder->array_node('token_handler')->example(['id' => 'App\Security\CustomTokenHandler'])->accept_and_wrap(['string'], 'id')->validate()->if_true(static fn($v): bool => \is_array($v) && 1 < \count($v))->then(static fn() => throw new Invalid_Configuration_Exception('You cannot configure multiple token handlers.'))->end()->is_required()->validate()->if_true(static fn($v): bool => \is_array($v) && !$v)->then(static fn() => throw new Invalid_Configuration_Exception('You must set a token handler.'))->end()->children();
        foreach ($this->token_handler_factories as $factory) {
            $factory->add_configuration($token_handler_node_builder);
        }
        $token_handler_node_builder->end();
    }
    public function get_priority(): int
    {
        return self::PRIORITY;
    }
    public function get_key(): string
    {
        return 'access_token';
    }
    public function create_authenticator(Container_Builder $container, string $firewall_name, array $config, ?string $user_provider_id): string
    {
        $success_handler = isset($config['success_handler']) ? new Reference($this->create_authentication_success_handler($container, $firewall_name, $config)) : null;
        $failure_handler = isset($config['failure_handler']) ? new Reference($this->create_authentication_failure_handler($container, $firewall_name, $config)) : null;
        $authenticator_id = \sprintf('security.authenticator.access_token.%s', $firewall_name);
        $extractor_id = $this->create_extractor($container, $firewall_name, $config['token_extractors']);
        $token_handler_id = $this->create_token_handler($container, $firewall_name, $config['token_handler'], $user_provider_id);
        $container->set_definition($authenticator_id, new Child_Definition('security.authenticator.access_token'))->replace_argument(0, new Reference($token_handler_id))->replace_argument(1, new Reference($extractor_id))->replace_argument(2, $user_provider_id ? new Reference($user_provider_id) : null)->replace_argument(3, $success_handler)->replace_argument(4, $failure_handler)->replace_argument(5, $config['realm']);
        return $authenticator_id;
    }
    /**
     * @param array<string> $extractors
     */
    private function create_extractor(Container_Builder $container, string $firewall_name, array $extractors): string
    {
        $aliases = ['query_string' => 'security.access_token_extractor.query_string', 'request_body' => 'security.access_token_extractor.request_body', 'header' => 'security.access_token_extractor.header'];
        $extractors = array_map(static fn(string $extractor): string => $aliases[$extractor] ?? $extractor, $extractors);
        if (1 === \count($extractors)) {
            return current($extractors);
        }
        $extractor_id = \sprintf('security.authenticator.access_token.chain_extractor.%s', $firewall_name);
        $container->set_definition($extractor_id, new Child_Definition('security.authenticator.access_token.chain_extractor'))->replace_argument(0, array_map(static fn(string $extractor_id): Reference => new Reference($extractor_id), $extractors));
        return $extractor_id;
    }
    private function create_token_handler(Container_Builder $container, string $firewall_name, array $config, ?string $user_provider_id): string
    {
        $key = array_keys($config)[0];
        $id = \sprintf('security.access_token_handler.%s', $firewall_name);
        foreach ($this->token_handler_factories as $factory) {
            if ($key !== $factory->get_key()) {
                continue;
            }
            $factory->create($container, $id, $config[$key], $user_provider_id);
        }
        return $id;
    }
}