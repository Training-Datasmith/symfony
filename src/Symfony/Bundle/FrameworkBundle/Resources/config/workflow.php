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

use Symfony\Component\Workflow\Event_Listener\Expression_Language;
use Symfony\Component\Workflow\Marking_Store\Method_Marking_Store;
use Symfony\Component\Workflow\Registry;
use Symfony\Component\Workflow\State_Machine;
use Symfony\Component\Workflow\Workflow;
return static function (Container_Configurator $container): void {
    $container->services()->set('workflow.abstract', Workflow::class)->args([abstract_arg('workflow definition'), abstract_arg('marking store'), service('event_dispatcher')->ignore_on_invalid(), abstract_arg('workflow name'), abstract_arg('events to dispatch')])->abstract()->set('state_machine.abstract', State_Machine::class)->args([abstract_arg('workflow definition'), abstract_arg('marking store'), service('event_dispatcher')->ignore_on_invalid(), abstract_arg('workflow name'), abstract_arg('events to dispatch')])->abstract()->set('workflow.marking_store.method', Method_Marking_Store::class)->abstract()->set('workflow.registry', Registry::class)->alias(Registry::class, 'workflow.registry')->set('workflow.security.expression_language', Expression_Language::class);
};