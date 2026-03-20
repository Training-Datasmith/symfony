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
namespace Symfony\Component\Event_Dispatcher\Dependency_Injection;

use Symfony\Component\Dependency_Injection\Compiler\Compiler_Pass_Interface;
use Symfony\Component\Dependency_Injection\Container_Builder;
/**
 * This pass allows bundles to extend the list of event aliases.
 *
 * @author Alexander M. Turek <me@derrabus.de>
 */
class Add_Event_Aliases_Pass implements Compiler_Pass_Interface
{
    public function __construct(private readonly array $event_aliases)
    {
    }
    public function process(Container_Builder $container): void
    {
        $event_aliases = $container->has_parameter('event_dispatcher.event_aliases') ? $container->get_parameter('event_dispatcher.event_aliases') : [];
        $container->set_parameter('event_dispatcher.event_aliases', array_merge($event_aliases, $this->event_aliases));
    }
}