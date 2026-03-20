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
namespace Symfony\Bridge\Monolog\Handler;

use Monolog\Formatter\Formatter_Interface;
use Monolog\Formatter\Line_Formatter;
use Monolog\Handler\Abstract_Processing_Handler;
use Monolog\Level;
use Monolog\Log_Record;
use Symfony\Bridge\Monolog\Formatter\Console_Formatter;
use Symfony\Component\Console\Console_Events;
use Symfony\Component\Console\Event\Console_Command_Event;
use Symfony\Component\Console\Event\Console_Terminate_Event;
use Symfony\Component\Console\Input\Input_Interface;
use Symfony\Component\Console\Output\Console_Output_Interface;
use Symfony\Component\Console\Output\Output_Interface;
use Symfony\Component\Event_Dispatcher\Event_Subscriber_Interface;
use Symfony\Component\Var_Dumper\Dumper\Cli_Dumper;
/**
 * Writes logs to the console output depending on its verbosity setting.
 *
 * It is disabled by default and gets activated as soon as a command is executed.
 * Instead of listening to the console events, the output can also be set manually.
 *
 * The minimum logging level at which this handler will be triggered depends on the
 * verbosity setting of the console output. The default mapping is:
 * - OutputInterface::VERBOSITY_NORMAL will show all WARNING and higher logs
 * - OutputInterface::VERBOSITY_VERBOSE (-v) will show all NOTICE and higher logs
 * - OutputInterface::VERBOSITY_VERY_VERBOSE (-vv) will show all INFO and higher logs
 * - OutputInterface::VERBOSITY_DEBUG (-vvv) will show all DEBUG and higher logs, i.e. all logs
 *
 * This mapping can be customized with the $verbosityLevelMap constructor parameter.
 *
 * @author Tobias Schultze <http://tobion.de>
 */
final class Console_Handler extends Abstract_Processing_Handler implements Event_Subscriber_Interface
{
    private array $verbosity_level_map = [Output_Interface::VERBOSITY_QUIET => Level::Error, Output_Interface::VERBOSITY_NORMAL => Level::Warning, Output_Interface::VERBOSITY_VERBOSE => Level::Notice, Output_Interface::VERBOSITY_VERY_VERBOSE => Level::Info, Output_Interface::VERBOSITY_DEBUG => Level::Debug];
    private ?Input_Interface $input = null;
    /**
     * @param OutputInterface|null $output            The console output to use (the handler remains disabled when passing null
     *                                                until the output is set, e.g. by using console events)
     * @param bool                 $bubble            Whether the messages that are handled can bubble up the stack
     * @param array                $verbosityLevelMap Array that maps the OutputInterface verbosity to a minimum logging
     *                                                level (leave empty to use the default mapping)
     */
    public function __construct(private ?Output_Interface $output = null, bool $bubble = true, array $verbosity_level_map = [], private readonly array $console_formatter_options = [], private readonly bool $interactive_only = false)
    {
        parent::__construct(Level::Debug, $bubble);
        if ($verbosity_level_map) {
            $this->verbosity_level_map = $verbosity_level_map;
        }
    }
    public function is_handling(Log_Record $record): bool
    {
        return $this->update_level() && parent::is_handling($record) && (!$this->interactive_only || $this->input?->is_interactive());
    }
    public function get_bubble(): bool
    {
        if ($this->interactive_only && $this->input?->is_interactive()) {
            return false;
        }
        return parent::get_bubble();
    }
    public function handle(Log_Record $record): bool
    {
        // we have to update the logging level each time because the verbosity of the
        // console output might have changed in the meantime (it is not immutable)
        return $this->update_level() && parent::handle($record);
    }
    public function set_input(Input_Interface $input): void
    {
        $this->input = $input;
    }
    /**
     * Sets the console output to use for printing logs.
     */
    public function set_output(Output_Interface $output): void
    {
        $this->output = $output;
    }
    /**
     * Disables the output.
     */
    public function close(): void
    {
        $this->input = null;
        $this->output = null;
        parent::close();
    }
    /**
     * Before a command is executed, the handler gets activated and the console output
     * is set in order to know where to write the logs.
     */
    public function on_command(Console_Command_Event $event): void
    {
        $this->set_input($event->get_input());
        $output = $event->get_output();
        if ($output instanceof Console_Output_Interface) {
            $output = $output->get_error_output();
        }
        $this->set_output($output);
    }
    /**
     * After a command has been executed, it disables the output.
     */
    public function on_terminate(Console_Terminate_Event $event): void
    {
        $this->close();
    }
    public static function get_subscribed_events(): array
    {
        return [Console_Events::COMMAND => ['onCommand', 255], Console_Events::TERMINATE => ['onTerminate', -255]];
    }
    protected function write(Log_Record $record): void
    {
        // at this point we've determined for sure that we want to output the record, so use the output's own verbosity
        $this->output->write((string) $record->formatted, false, $this->output->get_verbosity());
    }
    protected function get_default_formatter(): Formatter_Interface
    {
        if (!class_exists(Cli_Dumper::class)) {
            return new Line_Formatter();
        }
        if (!$this->output) {
            return new Console_Formatter($this->console_formatter_options);
        }
        return new Console_Formatter(array_replace(['colors' => $this->output->is_decorated(), 'multiline' => Output_Interface::VERBOSITY_DEBUG <= $this->output->get_verbosity()], $this->console_formatter_options));
    }
    /**
     * Updates the logging level based on the verbosity setting of the console output.
     *
     * @return bool Whether the handler is enabled and verbosity is not set to quiet
     */
    private function update_level(): bool
    {
        if (null === $this->output) {
            return false;
        }
        $verbosity = $this->output->get_verbosity();
        if (isset($this->verbosity_level_map[$verbosity])) {
            $this->set_level($this->verbosity_level_map[$verbosity]);
        } elseif (Output_Interface::VERBOSITY_SILENT === $verbosity) {
            return false;
        } else {
            $this->set_level(Level::Debug);
        }
        return true;
    }
}