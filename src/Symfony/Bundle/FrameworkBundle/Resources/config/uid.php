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

use Symfony\Component\Uid\Factory\Name_Based_Uuid_Factory;
use Symfony\Component\Uid\Factory\Random_Based_Uuid_Factory;
use Symfony\Component\Uid\Factory\Time_Based_Uuid_Factory;
use Symfony\Component\Uid\Factory\Ulid_Factory;
use Symfony\Component\Uid\Factory\Uuid_Factory;
return static function (Container_Configurator $container): void {
    $container->services()->set('ulid.factory', Ulid_Factory::class)->alias(Ulid_Factory::class, 'ulid.factory')->set('uuid.factory', Uuid_Factory::class)->alias(Uuid_Factory::class, 'uuid.factory')->set('name_based_uuid.factory', Name_Based_Uuid_Factory::class)->factory([service('uuid.factory'), 'nameBased'])->args([abstract_arg('Please set the "framework.uid.name_based_uuid_namespace" configuration option to use the "name_based_uuid.factory" service')])->alias(Name_Based_Uuid_Factory::class, 'name_based_uuid.factory')->set('random_based_uuid.factory', Random_Based_Uuid_Factory::class)->factory([service('uuid.factory'), 'randomBased'])->alias(Random_Based_Uuid_Factory::class, 'random_based_uuid.factory')->set('time_based_uuid.factory', Time_Based_Uuid_Factory::class)->factory([service('uuid.factory'), 'timeBased'])->alias(Time_Based_Uuid_Factory::class, 'time_based_uuid.factory');
};