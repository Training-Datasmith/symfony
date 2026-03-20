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
namespace Symfony\Bundle\Framework_Bundle\Command;

use Psr\Container\Container_Interface;
use Symfony\Bundle\Framework_Bundle\Console\Helper\Descriptor_Helper;
use Symfony\Component\Console\Attribute\As_Command;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Completion\Completion_Input;
use Symfony\Component\Console\Completion\Completion_Suggestions;
use Symfony\Component\Console\Input\Input_Argument;
use Symfony\Component\Console\Input\Input_Interface;
use Symfony\Component\Console\Input\Input_Option;
use Symfony\Component\Console\Output\Output_Interface;
use Symfony\Component\Console\Style\Symfony_Style;
use Symfony\Component\Event_Dispatcher\Event_Dispatcher_Interface;
use Symfony\Contracts\Service\Service_Provider_Interface;
/**
 * A console command for retrieving information about event dispatcher.
 *
 * @author Matthieu Auger <mail@matthieuauger.com>
 *
 * @final
 */
#[As_Command(name: 'debug:event-dispatcher', description: 'Display configured listeners for an application')]
class Event_Dispatcher_Debug_Command extends Command
{
    private const DEFAULT_DISPATCHER = 'event_dispatcher';
    public function __construct(private readonly Container_Interface $dispatchers)
    {
        parent::__construct();
    }
    protected function configure(): void
    {
        $this->set_definition([new Input_Argument('event', Input_Argument::OPTIONAL, 'An event name or a part of the event name'), new Input_Option('dispatcher', null, Input_Option::VALUE_REQUIRED, 'To view events of a specific event dispatcher', self::DEFAULT_DISPATCHER), new Input_Option('format', null, Input_Option::VALUE_REQUIRED, \sprintf('The output format ("%s")', implode('", "', $this->get_available_format_options())), 'txt'), new Input_Option('raw', null, Input_Option::VALUE_NONE, 'To output raw description')])->set_help(<<<'EOF'
        The <info>%command.name%</info> command displays all configured listeners:
        
          <info>php %command.full_name%</info>
        
        To get specific listeners for an event, specify its name:
        
          <info>php %command.full_name% kernel.request</info>
        
        The <info>--format</info> option specifies the format of the command output:
        
          <info>php %command.full_name% --format=json</info>
        EOF);
    }
    /**
     * @throws \LogicException
     */
    protected function execute(Input_Interface $input, Output_Interface $output): int
    {
        $io = new Symfony_Style($input, $output);
        $options = [];
        $dispatcher_service_name = $input->get_option('dispatcher');
        if (!$this->dispatchers->has($dispatcher_service_name)) {
            $io->get_error_style()->error(\sprintf('Event dispatcher "%s" is not available.', $dispatcher_service_name));
            return 1;
        }
        $dispatcher = $this->dispatchers->get($dispatcher_service_name);
        if ($event = $input->get_argument('event')) {
            if ($dispatcher->has_listeners($event)) {
                $options = ['event' => $event];
            } else {
                // if there is no direct match, try find partial matches
                $events = $this->search_for_event($dispatcher, $event);
                if (0 === \count($events)) {
                    $io->get_error_style()->warning(\sprintf('The event "%s" does not have any registered listeners.', $event));
                    return 0;
                }
                if (1 === \count($events)) {
                    $options = ['event' => $events[array_key_first($events)]];
                } else {
                    $options = ['events' => $events];
                }
            }
        }
        $helper = new Descriptor_Helper();
        if (self::DEFAULT_DISPATCHER !== $dispatcher_service_name) {
            $options['dispatcher_service_name'] = $dispatcher_service_name;
        }
        $options['format'] = $input->get_option('format');
        $options['raw_text'] = $input->get_option('raw');
        $options['output'] = $io;
        $helper->describe($io, $dispatcher, $options);
        return 0;
    }
    public function complete(Completion_Input $input, Completion_Suggestions $suggestions): void
    {
        if ($input->must_suggest_argument_values_for('event')) {
            $dispatcher_service_name = $input->get_option('dispatcher');
            if ($this->dispatchers->has($dispatcher_service_name)) {
                $dispatcher = $this->dispatchers->get($dispatcher_service_name);
                $suggestions->suggest_values(array_keys($dispatcher->get_listeners()));
            }
            return;
        }
        if ($input->must_suggest_option_values_for('dispatcher')) {
            if ($this->dispatchers instanceof Service_Provider_Interface) {
                $suggestions->suggest_values(array_keys($this->dispatchers->get_provided_services()));
            }
            return;
        }
        if ($input->must_suggest_option_values_for('format')) {
            $suggestions->suggest_values($this->get_available_format_options());
        }
    }
    private function search_for_event(Event_Dispatcher_Interface $dispatcher, string $needle): array
    {
        $output = [];
        $lc_needle = strtolower($needle);
        $all_events = array_keys($dispatcher->get_listeners());
        foreach ($all_events as $event) {
            if (str_contains(strtolower((string) $event), $lc_needle)) {
                $output[] = $event;
            }
        }
        return $output;
    }
    /** @return string[] */
    private function get_available_format_options(): array
    {
        return (new Descriptor_Helper())->get_formats();
    }
}