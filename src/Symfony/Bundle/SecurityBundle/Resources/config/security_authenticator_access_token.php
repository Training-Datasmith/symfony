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
namespace Symfony\Component\Dependency_Injection\Loader\Configurator;

use Jose\Component\Core\Algorithm_Manager;
use Jose\Component\Core\Algorithm_Manager_Factory;
use Jose\Component\Core\JWK;
use Jose\Component\Core\Jwk_Set;
use Jose\Component\Encryption\Algorithm\Content_Encryption\A128CBCHS256;
use Jose\Component\Encryption\Algorithm\Content_Encryption\A128GCM;
use Jose\Component\Encryption\Algorithm\Content_Encryption\A192CBCHS384;
use Jose\Component\Encryption\Algorithm\Content_Encryption\A192GCM;
use Jose\Component\Encryption\Algorithm\Content_Encryption\A256CBCHS512;
use Jose\Component\Encryption\Algorithm\Content_Encryption\A256GCM;
use Jose\Component\Encryption\Algorithm\Key_Encryption\ECDHES;
use Jose\Component\Encryption\Algorithm\Key_Encryption\ECDHSS;
use Jose\Component\Encryption\Algorithm\Key_Encryption\RSAOAEP;
use Jose\Component\Signature\Algorithm\ES256;
use Jose\Component\Signature\Algorithm\ES384;
use Jose\Component\Signature\Algorithm\ES512;
use Jose\Component\Signature\Algorithm\PS256;
use Jose\Component\Signature\Algorithm\PS384;
use Jose\Component\Signature\Algorithm\PS512;
use Jose\Component\Signature\Algorithm\RS256;
use Jose\Component\Signature\Algorithm\RS384;
use Jose\Component\Signature\Algorithm\RS512;
use Symfony\Component\Security\Http\Access_Token\Chain_Access_Token_Extractor;
use Symfony\Component\Security\Http\Access_Token\Form_Encoded_Body_Extractor;
use Symfony\Component\Security\Http\Access_Token\Header_Access_Token_Extractor;
use Symfony\Component\Security\Http\Access_Token\O_Auth2\Oauth2token_Handler;
use Symfony\Component\Security\Http\Access_Token\Oidc\Oidc_Token_Generator;
use Symfony\Component\Security\Http\Access_Token\Oidc\Oidc_Token_Handler;
use Symfony\Component\Security\Http\Access_Token\Oidc\Oidc_User_Info_Token_Handler;
use Symfony\Component\Security\Http\Access_Token\Query_Access_Token_Extractor;
use Symfony\Component\Security\Http\Authenticator\Access_Token_Authenticator;
use Symfony\Contracts\Http_Client\Http_Client_Interface;
return static function (Container_Configurator $container): void {
    $container->services()->set('security.access_token_extractor.header', Header_Access_Token_Extractor::class)->set('security.access_token_extractor.query_string', Query_Access_Token_Extractor::class)->set('security.access_token_extractor.request_body', Form_Encoded_Body_Extractor::class)->set('security.authenticator.access_token', Access_Token_Authenticator::class)->abstract()->args([abstract_arg('access token handler'), abstract_arg('access token extractor'), null, null, null, null])->set('security.authenticator.access_token.chain_extractor', Chain_Access_Token_Extractor::class)->abstract()->args([abstract_arg('access token extractors')])->set('security.access_token_handler.oidc_user_info.http_client', Http_Client_Interface::class)->abstract()->factory([service('http_client'), 'withOptions'])->args([abstract_arg('http client options')])->set('security.access_token_handler.oidc_user_info', Oidc_User_Info_Token_Handler::class)->abstract()->args([abstract_arg('http client'), service('logger')->null_on_invalid(), abstract_arg('claim')])->set('security.access_token_handler.oidc', Oidc_Token_Handler::class)->abstract()->args([abstract_arg('signature algorithm'), abstract_arg('signature key'), abstract_arg('audience'), abstract_arg('issuers'), 'sub', service('logger')->null_on_invalid(), service('clock')])->set('security.access_token_handler.oidc_discovery.http_client', Http_Client_Interface::class)->abstract()->factory([service('http_client'), 'withOptions'])->args([abstract_arg('http client options')])->set('security.access_token_handler.oidc.jwk', JWK::class)->abstract()->deprecate('symfony/security-http', '7.1', 'The "%service_id%" service is deprecated. Please use "security.access_token_handler.oidc.jwkset" instead')->factory([JWK::class, 'createFromJson'])->args([abstract_arg('signature key')])->set('security.access_token_handler.oidc.jwkset', Jwk_Set::class)->abstract()->factory([Jwk_Set::class, 'createFromJson'])->args([abstract_arg('signature keyset')])->set('security.access_token_handler.oidc.algorithm_manager_factory', Algorithm_Manager_Factory::class)->args([tagged_iterator('security.access_token_handler.oidc.signature_algorithm')])->set('security.access_token_handler.oidc.signature', Algorithm_Manager::class)->abstract()->factory([service('security.access_token_handler.oidc.algorithm_manager_factory'), 'create'])->args([abstract_arg('signature algorithms')])->set('security.access_token_handler.oidc.signature.ES256', ES256::class)->tag('security.access_token_handler.oidc.signature_algorithm')->set('security.access_token_handler.oidc.signature.ES384', ES384::class)->tag('security.access_token_handler.oidc.signature_algorithm')->set('security.access_token_handler.oidc.signature.ES512', ES512::class)->tag('security.access_token_handler.oidc.signature_algorithm')->set('security.access_token_handler.oidc.signature.RS256', RS256::class)->tag('security.access_token_handler.oidc.signature_algorithm')->set('security.access_token_handler.oidc.signature.RS384', RS384::class)->tag('security.access_token_handler.oidc.signature_algorithm')->set('security.access_token_handler.oidc.signature.RS512', RS512::class)->tag('security.access_token_handler.oidc.signature_algorithm')->set('security.access_token_handler.oidc.signature.PS256', PS256::class)->tag('security.access_token_handler.oidc.signature_algorithm')->set('security.access_token_handler.oidc.signature.PS384', PS384::class)->tag('security.access_token_handler.oidc.signature_algorithm')->set('security.access_token_handler.oidc.signature.PS512', PS512::class)->tag('security.access_token_handler.oidc.signature_algorithm')->set('security.access_token_handler.oidc.encryption_algorithm_manager_factory', Algorithm_Manager_Factory::class)->args([tagged_iterator('security.access_token_handler.oidc.encryption_algorithm')])->set('security.access_token_handler.oidc.encryption', Algorithm_Manager::class)->abstract()->factory([service('security.access_token_handler.oidc.encryption_algorithm_manager_factory'), 'create'])->args([abstract_arg('encryption algorithms')])->set('security.access_token_handler.oidc.encryption.RSAOAEP', RSAOAEP::class)->tag('security.access_token_handler.oidc.encryption_algorithm')->set('security.access_token_handler.oidc.encryption.ECDHES', ECDHES::class)->tag('security.access_token_handler.oidc.encryption_algorithm')->set('security.access_token_handler.oidc.encryption.ECDHSS', ECDHSS::class)->tag('security.access_token_handler.oidc.encryption_algorithm')->set('security.access_token_handler.oidc.encryption.A128CBCHS256', A128CBCHS256::class)->tag('security.access_token_handler.oidc.encryption_algorithm')->set('security.access_token_handler.oidc.encryption.A192CBCHS384', A192CBCHS384::class)->tag('security.access_token_handler.oidc.encryption_algorithm')->set('security.access_token_handler.oidc.encryption.A256CBCHS512', A256CBCHS512::class)->tag('security.access_token_handler.oidc.encryption_algorithm')->set('security.access_token_handler.oidc.encryption.A128GCM', A128GCM::class)->tag('security.access_token_handler.oidc.encryption_algorithm')->set('security.access_token_handler.oidc.encryption.A192GCM', A192GCM::class)->tag('security.access_token_handler.oidc.encryption_algorithm')->set('security.access_token_handler.oidc.encryption.A256GCM', A256GCM::class)->tag('security.access_token_handler.oidc.encryption_algorithm')->set('security.access_token_handler.oauth2', Oauth2token_Handler::class)->abstract()->args([service('http_client'), service('logger')->null_on_invalid()])->set('security.access_token_handler.oidc.generator', Oidc_Token_Generator::class)->abstract()->args([abstract_arg('signature algorithm'), abstract_arg('signature key'), abstract_arg('audience'), abstract_arg('issuers'), abstract_arg('claim'), service('clock')]);
};