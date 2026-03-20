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
namespace Symfony\Component\Console\Command;

use Symfony\Component\Console\Descriptor\Application_Description;
use Symfony\Component\Console\Helper\Descriptor_Helper;
use Symfony\Component\Console\Input\Input_Argument;
use Symfony\Component\Console\Input\Input_Interface;
use Symfony\Component\Console\Input\Input_Option;
use Symfony\Component\Console\Output\Output_Interface;
/**
 * HelpCommand displays the help for a given command.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class Help_Command extends Command
{
    private Command $command;
    protected function configure(): void
    {
        $this->ignore_validation_errors();
        $this->set_name('help')->set_definition([new Input_Argument('command_name', Input_Argument::OPTIONAL, 'The command name', 'help', fn(): array => array_keys((new Application_Description($this->get_application()))->get_commands())), new Input_Option('format', null, Input_Option::VALUE_REQUIRED, 'The output format (txt, xml, json, or md)', 'txt', static fn(): array => (new Descriptor_Helper())->get_formats()), new Input_Option('raw', null, Input_Option::VALUE_NONE, 'To output raw command help')])->set_description('Display help for a command')->set_help(<<<'EOF'
        The <info>%command.name%</info> command displays help for a given command:
        
          <info>%command.full_name% list</info>
        
        You can also output the help in other formats by using the <info>--format</info> option:
        
          <info>%command.full_name% --format=xml list</info>
        
        To display the list of available commands, please use the <info>list</info> command.
        EOF);
    }
    public function set_command(Command $command): void
    {
        $this->command = $command;
    }
    protected function execute(Input_Interface $input, Output_Interface $output): int
    {
        $this->command ??= $this->get_application()->find($input->get_argument('command_name'));
        $helper = new Descriptor_Helper();
        $helper->describe($output, $this->command, ['format' => $input->get_option('format'), 'raw_text' => $input->get_option('raw')]);
        unset($this->command);
        return 0;
    }
}