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
namespace Symfony\Bundle\Security_Bundle\Dependency_Injection\Security\User_Provider;

use Symfony\Component\Config\Definition\Builder\Node_Definition;
use Symfony\Component\Dependency_Injection\Container_Builder;
/**
 * UserProviderFactoryInterface is the interface for all user provider factories.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Christophe Coevoet <stof@notk.org>
 */
interface User_Provider_Factory_Interface
{
    public function create(Container_Builder $container, string $id, array $config): void;
    public function get_key(): string;
    public function add_configuration(Node_Definition $builder): void;
}