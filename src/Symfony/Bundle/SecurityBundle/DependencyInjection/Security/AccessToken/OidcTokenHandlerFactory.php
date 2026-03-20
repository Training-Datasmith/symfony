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

use Jose\Component\Core\Algorithm;
use Symfony\Component\Config\Definition\Builder\Node_Builder;
use Symfony\Component\Dependency_Injection\Child_Definition;
use Symfony\Component\Dependency_Injection\Container_Builder;
use Symfony\Component\Dependency_Injection\Exception\LogicException;
use Symfony\Component\Dependency_Injection\Reference;
use Symfony\Component\Security\Http\Command\Oidc_Token_Generate_Command;
use Symfony\Contracts\Http_Client\Http_Client_Interface;
/**
 * Configures a token handler for decoding and validating an OIDC token.
 */
class Oidc_Token_Handler_Factory implements Token_Handler_Factory_Interface
{
    public function create(Container_Builder $container, string $id, array|string $config): void
    {
        $token_handler_definition = $container->set_definition($id, (new Child_Definition('security.access_token_handler.oidc'))->replace_argument(2, $config['audience'])->replace_argument(3, $config['issuers'])->replace_argument(4, $config['claim'])->add_tag('container.reversible'));
        if (!Container_Builder::will_be_available('web-token/jwt-library', Algorithm::class, ['symfony/security-bundle'])) {
            throw new LogicException('You cannot use the "oidc" token handler since "web-token/jwt-library" is not installed. Try running "composer require web-token/jwt-library".');
        }
        $token_handler_definition->replace_argument(0, (new Child_Definition('security.access_token_handler.oidc.signature'))->replace_argument(0, $config['algorithms']));
        if (isset($config['discovery'])) {
            if (!Container_Builder::will_be_available('symfony/http-client', Http_Client_Interface::class, ['symfony/security-bundle'])) {
                throw new LogicException('You cannot use the "oidc" token handler with "discovery" since the HttpClient component is not installed. Try running "composer require symfony/http-client".');
            }
            // disable JWKSet argument
            $token_handler_definition->replace_argument(1, null);
            $clients = [];
            foreach ($config['discovery']['base_uri'] as $uri) {
                $clients[] = (new Child_Definition('security.access_token_handler.oidc_discovery.http_client'))->replace_argument(0, ['base_uri' => $uri]);
            }
            $token_handler_definition->add_method_call('enableDiscovery', [new Reference($config['discovery']['cache']['id']), $clients, "{$id}.oidc_configuration"]);
            return;
        }
        $token_handler_definition->replace_argument(1, (new Child_Definition('security.access_token_handler.oidc.jwkset'))->replace_argument(0, $config['keyset']));
        if ($config['encryption']['enabled']) {
            $algorithm_manager = (new Child_Definition('security.access_token_handler.oidc.encryption'))->replace_argument(0, $config['encryption']['algorithms']);
            $keyset = (new Child_Definition('security.access_token_handler.oidc.jwkset'))->replace_argument(0, $config['encryption']['keyset']);
            $token_handler_definition->add_method_call('enableJweSupport', [$keyset, $algorithm_manager, $config['encryption']['enforce']]);
        }
        // Generate command
        if (!class_exists(Oidc_Token_Generate_Command::class)) {
            return;
        }
        if (!$container->has_definition('security.access_token_handler.oidc.command.generate')) {
            $container->register('security.access_token_handler.oidc.command.generate', Oidc_Token_Generate_Command::class)->add_tag('console.command');
        }
        $firewall = substr($id, \strlen('security.access_token_handler.'));
        $container->get_definition('security.access_token_handler.oidc.command.generate')->add_method_call('addGenerator', [$firewall, (new Child_Definition('security.access_token_handler.oidc.generator'))->replace_argument(0, (new Child_Definition('security.access_token_handler.oidc.signature'))->replace_argument(0, $config['algorithms']))->replace_argument(1, (new Child_Definition('security.access_token_handler.oidc.jwkset'))->replace_argument(0, $config['keyset']))->replace_argument(2, $config['audience'])->replace_argument(3, $config['issuers'])->replace_argument(4, $config['claim']), $config['algorithms'], $config['issuers']]);
    }
    public function get_key(): string
    {
        return 'oidc';
    }
    public function add_configuration(Node_Builder $node): void
    {
        $node->array_node($this->get_key())->validate()->if_true(static fn($v): bool => !isset($v['discovery']) && !isset($v['keyset']))->then_invalid('You must set either "discovery" or "keyset".')->end()->children()->array_node('discovery')->info('Enable the OIDC discovery.')->children()->array_node('base_uri')->accept_and_wrap(['string'])->info('Base URI of the OIDC server.')->is_required()->scalar_prototype()->end()->end()->array_node('cache')->children()->scalar_node('id')->info('Cache service id to use to cache the OIDC discovery configuration.')->is_required()->cannot_be_empty()->end()->end()->end()->end()->end()->scalar_node('claim')->info('Claim which contains the user identifier (e.g.: sub, email..).')->default_value('sub')->end()->scalar_node('audience')->info('Audience set in the token, for validation purpose.')->is_required()->end()->array_node('issuers', 'issuer')->info('Issuers allowed to generate the token, for validation purpose.')->is_required()->scalar_prototype()->end()->end()->array_node('algorithms', 'algorithm')->info('Algorithms used to sign the token.')->is_required()->scalar_prototype()->end()->end()->scalar_node('keyset')->info('JSON-encoded JWKSet used to sign the token (must contain a list of valid public keys).')->end()->array_node('encryption')->can_be_enabled()->children()->boolean_node('enforce')->info('When enabled, the token shall be encrypted.')->default_false()->end()->array_node('algorithms', 'algorithm')->info('Algorithms used to decrypt the token.')->is_required()->requires_at_least_one_element()->scalar_prototype()->end()->end()->scalar_node('keyset')->info('JSON-encoded JWKSet used to decrypt the token (must contain a list of valid private keys).')->is_required()->end()->end()->end()->end()->end();
    }
}