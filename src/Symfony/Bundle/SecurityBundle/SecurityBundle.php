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
namespace Symfony\Bundle\Security_Bundle;

use Symfony\Bundle\Security_Bundle\Dependency_Injection\Compiler\Add_Expression_Language_Providers_Pass;
use Symfony\Bundle\Security_Bundle\Dependency_Injection\Compiler\Add_Security_Voters_Pass;
use Symfony\Bundle\Security_Bundle\Dependency_Injection\Compiler\Add_Session_Domain_Constraint_Pass;
use Symfony\Bundle\Security_Bundle\Dependency_Injection\Compiler\Clean_Remember_Me_Verifier_Pass;
use Symfony\Bundle\Security_Bundle\Dependency_Injection\Compiler\Make_Firewalls_Event_Dispatcher_Traceable_Pass;
use Symfony\Bundle\Security_Bundle\Dependency_Injection\Compiler\Register_Csrf_Features_Pass;
use Symfony\Bundle\Security_Bundle\Dependency_Injection\Compiler\Register_Entry_Point_Pass;
use Symfony\Bundle\Security_Bundle\Dependency_Injection\Compiler\Register_Global_Security_Event_Listeners_Pass;
use Symfony\Bundle\Security_Bundle\Dependency_Injection\Compiler\Register_Ldap_Locator_Pass;
use Symfony\Bundle\Security_Bundle\Dependency_Injection\Compiler\Register_Token_Usage_Tracking_Pass;
use Symfony\Bundle\Security_Bundle\Dependency_Injection\Compiler\Replace_Decorated_Remember_Me_Handler_Pass;
use Symfony\Bundle\Security_Bundle\Dependency_Injection\Compiler\Sort_Firewall_Listeners_Pass;
use Symfony\Bundle\Security_Bundle\Dependency_Injection\Security\Access_Token\Cas_Token_Handler_Factory;
use Symfony\Bundle\Security_Bundle\Dependency_Injection\Security\Access_Token\O_Auth2token_Handler_Factory;
use Symfony\Bundle\Security_Bundle\Dependency_Injection\Security\Access_Token\Oidc_Token_Handler_Factory;
use Symfony\Bundle\Security_Bundle\Dependency_Injection\Security\Access_Token\Oidc_User_Info_Token_Handler_Factory;
use Symfony\Bundle\Security_Bundle\Dependency_Injection\Security\Access_Token\Service_Token_Handler_Factory;
use Symfony\Bundle\Security_Bundle\Dependency_Injection\Security\Factory\Access_Token_Factory;
use Symfony\Bundle\Security_Bundle\Dependency_Injection\Security\Factory\Custom_Authenticator_Factory;
use Symfony\Bundle\Security_Bundle\Dependency_Injection\Security\Factory\Form_Login_Factory;
use Symfony\Bundle\Security_Bundle\Dependency_Injection\Security\Factory\Form_Login_Ldap_Factory;
use Symfony\Bundle\Security_Bundle\Dependency_Injection\Security\Factory\Http_Basic_Factory;
use Symfony\Bundle\Security_Bundle\Dependency_Injection\Security\Factory\Http_Basic_Ldap_Factory;
use Symfony\Bundle\Security_Bundle\Dependency_Injection\Security\Factory\Json_Login_Factory;
use Symfony\Bundle\Security_Bundle\Dependency_Injection\Security\Factory\Json_Login_Ldap_Factory;
use Symfony\Bundle\Security_Bundle\Dependency_Injection\Security\Factory\Login_Link_Factory;
use Symfony\Bundle\Security_Bundle\Dependency_Injection\Security\Factory\Login_Throttling_Factory;
use Symfony\Bundle\Security_Bundle\Dependency_Injection\Security\Factory\Remember_Me_Factory;
use Symfony\Bundle\Security_Bundle\Dependency_Injection\Security\Factory\Remote_User_Factory;
use Symfony\Bundle\Security_Bundle\Dependency_Injection\Security\Factory\X509Factory;
use Symfony\Bundle\Security_Bundle\Dependency_Injection\Security\User_Provider\In_Memory_Factory;
use Symfony\Bundle\Security_Bundle\Dependency_Injection\Security\User_Provider\Ldap_Factory;
use Symfony\Bundle\Security_Bundle\Dependency_Injection\Security_Extension;
use Symfony\Component\Dependency_Injection\Compiler\Pass_Config;
use Symfony\Component\Dependency_Injection\Container_Builder;
use Symfony\Component\Event_Dispatcher\Dependency_Injection\Add_Event_Aliases_Pass;
use Symfony\Component\Http_Kernel\Bundle\Bundle;
use Symfony\Component\Security\Core\Authentication_Events;
use Symfony\Component\Security\Http\Security_Events;
/**
 * Bundle.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class Security_Bundle extends Bundle
{
    public function build(Container_Builder $container): void
    {
        parent::build($container);
        /** @var SecurityExtension $extension */
        $extension = $container->get_extension('security');
        $extension->add_authenticator_factory(new Form_Login_Factory());
        $extension->add_authenticator_factory(new Form_Login_Ldap_Factory());
        $extension->add_authenticator_factory(new Json_Login_Factory());
        $extension->add_authenticator_factory(new Json_Login_Ldap_Factory());
        $extension->add_authenticator_factory(new Http_Basic_Factory());
        $extension->add_authenticator_factory(new Http_Basic_Ldap_Factory());
        $extension->add_authenticator_factory(new Remember_Me_Factory());
        $extension->add_authenticator_factory(new X509Factory());
        $extension->add_authenticator_factory(new Remote_User_Factory());
        $extension->add_authenticator_factory(new Custom_Authenticator_Factory());
        $extension->add_authenticator_factory(new Login_Throttling_Factory());
        $extension->add_authenticator_factory(new Login_Link_Factory());
        $extension->add_authenticator_factory(new Access_Token_Factory([new Service_Token_Handler_Factory(), new Oidc_User_Info_Token_Handler_Factory(), new Oidc_Token_Handler_Factory(), new Cas_Token_Handler_Factory(), new O_Auth2token_Handler_Factory()]));
        $extension->add_user_provider_factory(new In_Memory_Factory());
        $extension->add_user_provider_factory(new Ldap_Factory());
        $container->add_compiler_pass(new Add_Expression_Language_Providers_Pass());
        $container->add_compiler_pass(new Add_Security_Voters_Pass());
        $container->add_compiler_pass(new Add_Session_Domain_Constraint_Pass());
        $container->add_compiler_pass(new Clean_Remember_Me_Verifier_Pass());
        $container->add_compiler_pass(new Register_Csrf_Features_Pass());
        $container->add_compiler_pass(new Register_Token_Usage_Tracking_Pass(), Pass_Config::TYPE_BEFORE_OPTIMIZATION, 200);
        $container->add_compiler_pass(new Register_Ldap_Locator_Pass());
        $container->add_compiler_pass(new Register_Entry_Point_Pass());
        // must be registered after RegisterListenersPass (in the FrameworkBundle)
        $container->add_compiler_pass(new Register_Global_Security_Event_Listeners_Pass(), Pass_Config::TYPE_BEFORE_REMOVING, -200);
        // execute after ResolveChildDefinitionsPass optimization pass, to ensure class names are set
        $container->add_compiler_pass(new Sort_Firewall_Listeners_Pass(), Pass_Config::TYPE_BEFORE_REMOVING);
        $container->add_compiler_pass(new Replace_Decorated_Remember_Me_Handler_Pass(), Pass_Config::TYPE_OPTIMIZE);
        $container->add_compiler_pass(new Add_Event_Aliases_Pass(array_merge(Authentication_Events::ALIASES, Security_Events::ALIASES)));
        // must be registered before DecoratorServicePass
        $container->add_compiler_pass(new Make_Firewalls_Event_Dispatcher_Traceable_Pass(), Pass_Config::TYPE_BEFORE_OPTIMIZATION, 10);
    }
}