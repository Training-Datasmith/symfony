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
namespace Symfony\Bridge\Doctrine\Dependency_Injection\Compiler_Pass;

use Symfony\Bridge\Doctrine\Types\Ulid_Type;
use Symfony\Bridge\Doctrine\Types\Uuid_Type;
use Symfony\Component\Dependency_Injection\Compiler\Compiler_Pass_Interface;
use Symfony\Component\Dependency_Injection\Container_Builder;
use Symfony\Component\Uid\Abstract_Uid;
final class Register_Uid_Type_Pass implements Compiler_Pass_Interface
{
    public function process(Container_Builder $container): void
    {
        if (!class_exists(Abstract_Uid::class)) {
            return;
        }
        if (!$container->has_parameter('doctrine.dbal.connection_factory.types')) {
            return;
        }
        $type_definition = $container->get_parameter('doctrine.dbal.connection_factory.types');
        if (!isset($type_definition['uuid'])) {
            $type_definition['uuid'] = ['class' => Uuid_Type::class];
        }
        if (!isset($type_definition['ulid'])) {
            $type_definition['ulid'] = ['class' => Ulid_Type::class];
        }
        $container->set_parameter('doctrine.dbal.connection_factory.types', $type_definition);
    }
}