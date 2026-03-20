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
namespace Symfony\Bundle\Security_Bundle\Dependency_Injection\Security\Access_Token;

use Symfony\Component\Config\Definition\Builder\Node_Builder;
use Symfony\Component\Dependency_Injection\Child_Definition;
use Symfony\Component\Dependency_Injection\Container_Builder;
use Symfony\Component\Dependency_Injection\Exception\LogicException;
use Symfony\Component\Dependency_Injection\Reference;
use Symfony\Contracts\Cache\Cache_Interface;
use Symfony\Contracts\Http_Client\Http_Client_Interface;
/**
 * Configures a token handler for an OIDC server.
 */
class Oidc_User_Info_Token_Handler_Factory implements Token_Handler_Factory_Interface
{
    public function create(Container_Builder $container, string $id, array|string $config): void
    {
        $client_definition = (new Child_Definition('security.access_token_handler.oidc_user_info.http_client'))->replace_argument(0, ['base_uri' => $config['base_uri']]);
        if (isset($config['client'])) {
            $client_definition->set_factory([new Reference($config['client']), 'withOptions']);
        } elseif (!Container_Builder::will_be_available('symfony/http-client', Http_Client_Interface::class, ['symfony/security-bundle'])) {
            throw new LogicException('You cannot use the "oidc_user_info" token handler since the HttpClient component is not installed. Try running "composer require symfony/http-client".');
        }
        $token_handler_definition = $container->set_definition($id, new Child_Definition('security.access_token_handler.oidc_user_info'))->replace_argument(0, $client_definition)->replace_argument(2, $config['claim']);
        if (isset($config['discovery'])) {
            if (!Container_Builder::will_be_available('symfony/cache', Cache_Interface::class, ['symfony/security-bundle'])) {
                throw new LogicException('You cannot use the "oidc_user_info" token handler with "discovery" since the Cache component is not installed. Try running "composer require symfony/cache".');
            }
            $token_handler_definition->add_method_call('enableDiscovery', [new Reference($config['discovery']['cache']['id']), "{$id}.oidc_configuration"]);
        }
    }
    public function get_key(): string
    {
        return 'oidc_user_info';
    }
    public function add_configuration(Node_Builder $node): void
    {
        $node->array_node($this->get_key())->before_normalization()->if_string()->then(static fn($v): array => ['claim' => 'sub', 'base_uri' => $v])->end()->children()->scalar_node('base_uri')->info('Base URI of the userinfo endpoint on the OIDC server, or the OIDC server URI to use the discovery (require "discovery" to be configured).')->is_required()->cannot_be_empty()->end()->array_node('discovery')->info('Enable the OIDC discovery.')->children()->array_node('cache')->children()->scalar_node('id')->info('Cache service id to use to cache the OIDC discovery configuration.')->is_required()->cannot_be_empty()->end()->end()->end()->end()->end()->scalar_node('claim')->info('Claim which contains the user identifier (e.g. sub, email, etc.).')->default_value('sub')->cannot_be_empty()->end()->scalar_node('client')->info('HttpClient service id to use to call the OIDC server.')->end()->end()->end();
    }
}