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
use Symfony\Component\Dependency_Injection\Reference;
use Symfony\Component\Security\Http\Access_Token\Cas\Cas2Handler;
class Cas_Token_Handler_Factory implements Token_Handler_Factory_Interface
{
    public function create(Container_Builder $container, string $id, array|string $config): void
    {
        $container->set_definition($id, new Child_Definition('security.access_token_handler.cas'));
        $container->register('security.access_token_handler.cas', Cas2Handler::class)->set_arguments([new Reference('request_stack'), $config['validation_url'], $config['prefix'], $config['http_client'] ? new Reference($config['http_client']) : null]);
    }
    public function get_key(): string
    {
        return 'cas';
    }
    public function add_configuration(Node_Builder $node): void
    {
        $node->array_node($this->get_key())->children()->scalar_node('validation_url')->info('CAS server validation URL')->is_required()->end()->scalar_node('prefix')->info('CAS prefix')->default_value('cas')->end()->scalar_node('http_client')->info('HTTP Client service')->default_null()->end()->end()->end();
    }
}