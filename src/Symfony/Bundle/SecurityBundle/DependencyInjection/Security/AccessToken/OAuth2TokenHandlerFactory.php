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
/**
 * Configures a token handler for an OAuth2 Token Introspection endpoint.
 *
 * @internal
 */
class O_Auth2token_Handler_Factory implements Token_Handler_Factory_Interface
{
    public function create(Container_Builder $container, string $id, array|string $config): void
    {
        $container->set_definition($id, new Child_Definition('security.access_token_handler.oauth2'));
    }
    public function get_key(): string
    {
        return 'oauth2';
    }
    public function add_configuration(Node_Builder $node): void
    {
        $node->scalar_node($this->get_key())->end();
    }
}