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
 * ListCommand displays the list of all available commands for the application.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class List_Command extends Command
{
    protected function configure(): void
    {
        $this->set_name('list')->set_definition([new Input_Argument('namespace', Input_Argument::OPTIONAL, 'The namespace name', null, fn(): array => array_keys((new Application_Description($this->get_application()))->get_namespaces())), new Input_Option('raw', null, Input_Option::VALUE_NONE, 'To output raw command list'), new Input_Option('format', null, Input_Option::VALUE_REQUIRED, 'The output format (txt, xml, json, or md)', 'txt', static fn(): array => (new Descriptor_Helper())->get_formats()), new Input_Option('short', null, Input_Option::VALUE_NONE, 'To skip describing commands\' arguments')])->set_description('List commands')->set_help(<<<'EOF'
        The <info>%command.name%</info> command lists all commands:
        
          <info>%command.full_name%</info>
        
        You can also display the commands for a specific namespace:
        
          <info>%command.full_name% test</info>
        
        You can also output the information in other formats by using the <info>--format</info> option:
        
          <info>%command.full_name% --format=xml</info>
        
        It's also possible to get raw list of commands (useful for embedding command runner):
        
          <info>%command.full_name% --raw</info>
        EOF);
    }
    protected function execute(Input_Interface $input, Output_Interface $output): int
    {
        $helper = new Descriptor_Helper();
        $helper->describe($output, $this->get_application(), ['format' => $input->get_option('format'), 'raw_text' => $input->get_option('raw'), 'namespace' => $input->get_argument('namespace'), 'short' => $input->get_option('short')]);
        return 0;
    }
}