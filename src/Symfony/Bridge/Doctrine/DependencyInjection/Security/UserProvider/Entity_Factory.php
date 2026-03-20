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
namespace Symfony\Bridge\Doctrine\Dependency_Injection\Security\User_Provider;

use Symfony\Bundle\Security_Bundle\Dependency_Injection\Security\User_Provider\User_Provider_Factory_Interface;
use Symfony\Component\Config\Definition\Builder\Node_Definition;
use Symfony\Component\Dependency_Injection\Child_Definition;
use Symfony\Component\Dependency_Injection\Container_Builder;
/**
 * EntityFactory creates services for Doctrine user provider.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Christophe Coevoet <stof@notk.org>
 *
 * @final
 */
class Entity_Factory implements User_Provider_Factory_Interface
{
    public function __construct(private readonly string $key, private readonly string $provider_id)
    {
    }
    public function create(Container_Builder $container, string $id, array $config): void
    {
        $container->set_definition($id, new Child_Definition($this->provider_id))->add_argument($config['class'])->add_argument($config['property'])->add_argument($config['manager_name']);
    }
    public function get_key(): string
    {
        return $this->key;
    }
    public function add_configuration(Node_Definition $node): void
    {
        $node->children()->scalar_node('class')->is_required()->info('The full entity class name of your user class.')->cannot_be_empty()->end()->scalar_node('property')->default_null()->end()->scalar_node('manager_name')->default_null()->end()->end();
    }
}