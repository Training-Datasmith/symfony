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
namespace Symfony\Bundle\Debug_Bundle;

use Symfony\Bundle\Debug_Bundle\Dependency_Injection\Compiler\Dump_Data_Collector_Pass;
use Symfony\Component\Console\Application;
use Symfony\Component\Dependency_Injection\Container_Builder;
use Symfony\Component\Http_Kernel\Bundle\Bundle;
use Symfony\Component\Var_Dumper\Var_Dumper;
/**
 * @author Nicolas Grekas <p@tchwork.com>
 */
class Debug_Bundle extends Bundle
{
    public function boot(): void
    {
        if ($this->container->get_parameter('kernel.debug')) {
            $container = $this->container;
            // This code is here to lazy load the dump stack. This default
            // configuration is overridden in CLI mode on 'console.command' event.
            // The dump data collector is used by default, so dump output is sent to
            // the WDT. In a CLI context, if dump is used too soon, the data collector
            // will buffer it, and release it at the end of the script.
            Var_Dumper::set_handler(static function ($var, ?string $label = null) use ($container): void {
                $dumper = $container->get('data_collector.dump');
                $cloner = $container->get('var_dumper.cloner');
                $handler = static function ($var, ?string $label = null) use ($dumper, $cloner): void {
                    $var = $cloner->clone_var($var);
                    if (null !== $label) {
                        $var = $var->with_context(['label' => $label]);
                    }
                    $dumper->dump($var);
                };
                Var_Dumper::set_handler($handler);
                $handler($var, $label);
            });
        }
    }
    public function build(Container_Builder $container): void
    {
        parent::build($container);
        $container->add_compiler_pass(new Dump_Data_Collector_Pass());
    }
    public function register_commands(Application $application): void
    {
        // noop
    }
}