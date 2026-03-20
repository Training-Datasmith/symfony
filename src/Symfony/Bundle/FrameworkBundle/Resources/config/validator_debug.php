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

use Symfony\Component\Validator\Data_Collector\Validator_Data_Collector;
use Symfony\Component\Validator\Validator\Traceable_Validator;
return static function (Container_Configurator $container): void {
    $container->services()->set('debug.validator', Traceable_Validator::class)->decorate('validator', null, 255)->args([service('debug.validator.inner'), service('profiler.is_disabled_state_checker')->null_on_invalid()])->tag('kernel.reset', ['method' => 'reset'])->set('data_collector.validator', Validator_Data_Collector::class)->args([service('debug.validator')])->tag('data_collector', ['template' => '@WebProfiler/Collector/validator.html.twig', 'id' => 'validator', 'priority' => 320]);
};