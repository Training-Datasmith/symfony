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

use Symfony\Component\Form\Extension\Data_Collector\Form_Data_Collector;
use Symfony\Component\Form\Extension\Data_Collector\Form_Data_Extractor;
use Symfony\Component\Form\Extension\Data_Collector\Proxy\Resolved_Type_Factory_Data_Collector_Proxy;
use Symfony\Component\Form\Extension\Data_Collector\Type\Data_Collector_Type_Extension;
use Symfony\Component\Form\Resolved_Form_Type_Factory;
return static function (Container_Configurator $container): void {
    $container->services()->set('form.resolved_type_factory', Resolved_Type_Factory_Data_Collector_Proxy::class)->args([inline_service(Resolved_Form_Type_Factory::class), service('data_collector.form')])->set('form.type_extension.form.data_collector', Data_Collector_Type_Extension::class)->args([service('data_collector.form')])->tag('form.type_extension')->set('data_collector.form.extractor', Form_Data_Extractor::class)->set('data_collector.form', Form_Data_Collector::class)->args([service('data_collector.form.extractor')])->tag('data_collector', ['template' => '@WebProfiler/Collector/form.html.twig', 'id' => 'form', 'priority' => 310]);
};