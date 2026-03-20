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
namespace Symfony\Component\Http_Kernel\Event_Listener;

use Symfony\Component\Console\Console_Events;
use Symfony\Component\Console\Event\Console_Command_Event;
use Symfony\Component\Event_Dispatcher\Event_Subscriber_Interface;
use Symfony\Component\Var_Dumper\Cloner\Cloner_Interface;
use Symfony\Component\Var_Dumper\Dumper\Data_Dumper_Interface;
use Symfony\Component\Var_Dumper\Server\Connection;
use Symfony\Component\Var_Dumper\Var_Dumper;
/**
 * Configures dump() handler.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
class Dump_Listener implements Event_Subscriber_Interface
{
    /**
     * @param ?DataDumperInterface $profilerDumper The dumper to use when CLI profiling is enabled.
     *                                             If null, the default $dumper will be used instead.
     */
    public function __construct(private readonly Cloner_Interface $cloner, private readonly Data_Dumper_Interface $dumper, private readonly ?Connection $connection = null, private readonly ?Data_Dumper_Interface $profiler_dumper = null)
    {
    }
    public function configure(?Console_Command_Event $event = null): void
    {
        $input = $event?->get_input();
        $cloner = $this->cloner;
        $dumper = !$this->profiler_dumper || !$input?->has_option('profile') || !$input?->get_option('profile') ? $this->dumper : $this->profiler_dumper;
        $connection = $this->connection;
        Var_Dumper::set_handler(static function ($var, ?string $label = null) use ($cloner, $dumper, $connection): void {
            $data = $cloner->clone_var($var);
            if (null !== $label) {
                $data = $data->with_context(['label' => $label]);
            }
            if (!$connection || !$connection->write($data)) {
                $dumper->dump($data);
            }
        });
    }
    public static function get_subscribed_events(): array
    {
        if (!class_exists(Console_Events::class)) {
            return [];
        }
        // Register early to have a working dump() as early as possible
        return [Console_Events::COMMAND => ['configure', 1024]];
    }
}