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
/**
 * FormLoginLdapFactory creates services for form login ldap authentication.
 *
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 * @author Charles Sarrazin <charles@sarraz.in>
 *
 * @internal
 */
class Form_Login_Ldap_Factory extends Form_Login_Factory
{
    use Ldap_Factory_Trait;
    public function add_configuration(Node_Definition $node): void
    {
        parent::add_configuration($node);
        $node->children()->scalar_node('service')->default_value('ldap')->end()->scalar_node('dn_string')->default_value('{user_identifier}')->end()->scalar_node('query_string')->end()->scalar_node('search_dn')->default_value('')->end()->scalar_node('search_password')->default_value('')->end()->end();
    }
}