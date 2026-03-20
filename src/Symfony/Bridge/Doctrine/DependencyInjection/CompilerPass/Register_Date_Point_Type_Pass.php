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

use Symfony\Bridge\Doctrine\Types\Date_Point_Type;
use Symfony\Bridge\Doctrine\Types\Day_Point_Type;
use Symfony\Bridge\Doctrine\Types\Time_Point_Type;
use Symfony\Component\Clock\Date_Point;
use Symfony\Component\Dependency_Injection\Compiler\Compiler_Pass_Interface;
use Symfony\Component\Dependency_Injection\Container_Builder;
final class Register_Date_Point_Type_Pass implements Compiler_Pass_Interface
{
    public function process(Container_Builder $container): void
    {
        if (!class_exists(Date_Point::class)) {
            return;
        }
        if (!$container->has_parameter('doctrine.dbal.connection_factory.types')) {
            return;
        }
        $types = $container->get_parameter('doctrine.dbal.connection_factory.types');
        $types['date_point'] ??= ['class' => Date_Point_Type::class];
        $types['day_point'] ??= ['class' => Day_Point_Type::class];
        $types['time_point'] ??= ['class' => Time_Point_Type::class];
        $container->set_parameter('doctrine.dbal.connection_factory.types', $types);
    }
}