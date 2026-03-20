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

use Symfony\Component\Config\Definition\Configuration_Interface;
use Symfony\Component\Config\Definition\Dumper\Xml_Reference_Dumper;
use Symfony\Component\Config\Definition\Dumper\Yaml_Reference_Dumper;
use Symfony\Component\Console\Attribute\As_Command;
use Symfony\Component\Console\Completion\Completion_Input;
use Symfony\Component\Console\Completion\Completion_Suggestions;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Input\Input_Argument;
use Symfony\Component\Console\Input\Input_Interface;
use Symfony\Component\Console\Input\Input_Option;
use Symfony\Component\Console\Output\Output_Interface;
use Symfony\Component\Console\Style\Symfony_Style;
use Symfony\Component\Dependency_Injection\Extension\Configuration_Extension_Interface;
use Symfony\Component\Yaml\Yaml;
/**
 * A console command for dumping available configuration reference.
 *
 * @author Kevin Bond <kevinbond@gmail.com>
 * @author Wouter J <waldio.webdesign@gmail.com>
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 *
 * @final
 */
#[As_Command(name: 'config:dump-reference', description: 'Dump the default configuration for an extension')]
class Config_Dump_Reference_Command extends Abstract_Config_Command
{
    protected function configure(): void
    {
        $this->set_definition([new Input_Argument('name', Input_Argument::OPTIONAL, 'The Bundle name or the extension alias'), new Input_Argument('path', Input_Argument::OPTIONAL, 'The configuration option path'), new Input_Option('format', null, Input_Option::VALUE_REQUIRED, \sprintf('The output format ("%s")', implode('", "', $this->get_available_format_options())), 'yaml')])->set_help(<<<EOF
        The <info>%command.name%</info> command dumps the default configuration for an
        extension/bundle.
        
        Either the extension alias or bundle name can be used:
        
          <info>php %command.full_name% framework</info>
          <info>php %command.full_name% FrameworkBundle</info>
        
        The <info>--format</info> option specifies the format of the command output:
        
          <info>php %command.full_name% FrameworkBundle --format=json</info>
        
        For dumping a specific option, add its path as second argument (only available for the yaml format):
        
          <info>php %command.full_name% framework http_client.default_options</info>
        
        EOF);
    }
    /**
     * @throws \LogicException
     */
    protected function execute(Input_Interface $input, Output_Interface $output): int
    {
        $io = new Symfony_Style($input, $output);
        $error_io = $io->get_error_style();
        if (null === $name = $input->get_argument('name')) {
            $this->list_bundles($error_io);
            $this->list_non_bundle_extensions($error_io);
            $error_io->comment(['Provide the name of a bundle as the first argument of this command to dump its default configuration. (e.g. <comment>config:dump-reference FrameworkBundle</comment>)', 'For dumping a specific option, add its path as the second argument of this command. (e.g. <comment>config:dump-reference FrameworkBundle http_client.default_options</comment> to dump the <comment>framework.http_client.default_options</comment> configuration)']);
            return 0;
        }
        $extension = $this->find_extension($name);
        if ($extension instanceof Configuration_Interface) {
            $configuration = $extension;
        } else {
            $configuration = $extension->get_configuration([], $this->get_container_builder($this->get_application()->get_kernel()));
        }
        $this->validate_configuration($extension, $configuration);
        $format = $input->get_option('format');
        if ('yaml' === $format && !class_exists(Yaml::class)) {
            $error_io->error('Setting the "format" option to "yaml" requires the Symfony Yaml component. Try running "composer install symfony/yaml" or use "--format=xml" instead.');
            return 1;
        }
        $path = $input->get_argument('path');
        if (null !== $path && 'yaml' !== $format) {
            $error_io->error('The "path" option is only available for the "yaml" format.');
            return 1;
        }
        if ($name === $extension->get_alias()) {
            $message = \sprintf('Default configuration for extension with alias: "%s"', $name);
        } else {
            $message = \sprintf('Default configuration for "%s"', $name);
        }
        if (null !== $path) {
            $message .= \sprintf(' at path "%s"', $path);
        }
        if ($doc_url = $this->get_extension_doc_url($extension)) {
            $message .= \sprintf(' (see %s)', $doc_url);
        }
        switch ($format) {
            case 'yaml':
                $io->writeln(\sprintf('# %s', $message));
                $dumper = new Yaml_Reference_Dumper();
                break;
            case 'xml':
                $io->writeln(\sprintf('<!-- %s -->', $message));
                $dumper = new Xml_Reference_Dumper();
                break;
            default:
                $io->writeln($message);
                throw new InvalidArgumentException(\sprintf('Supported formats are "%s".', implode('", "', $this->get_available_format_options())));
        }
        $io->writeln(null === $path ? $dumper->dump($configuration) : $dumper->dump_at_path($configuration, $path));
        return 0;
    }
    public function complete(Completion_Input $input, Completion_Suggestions $suggestions): void
    {
        if ($input->must_suggest_argument_values_for('name')) {
            $suggestions->suggest_values($this->get_available_extensions());
            $suggestions->suggest_values($this->get_available_bundles());
        }
        if ($input->must_suggest_option_values_for('format')) {
            $suggestions->suggest_values($this->get_available_format_options());
        }
    }
    private function get_available_extensions(): array
    {
        $kernel = $this->get_application()->get_kernel();
        $extensions = [];
        foreach ($this->get_container_builder($kernel)->get_extensions() as $alias => $extension) {
            $extensions[] = $alias;
        }
        return $extensions;
    }
    private function get_available_bundles(): array
    {
        $bundles = [];
        foreach ($this->get_application()->get_kernel()->get_bundles() as $bundle) {
            $bundles[] = $bundle->get_name();
        }
        return $bundles;
    }
    /** @return string[] */
    private function get_available_format_options(): array
    {
        return ['yaml', 'xml'];
    }
    private function get_extension_doc_url(Configuration_Interface|Configuration_Extension_Interface $extension): ?string
    {
        $kernel = $this->get_application()->get_kernel();
        $container = $this->get_container_builder($kernel);
        $configuration = $extension instanceof Configuration_Interface ? $extension : $extension->get_configuration($container->get_extension_config($extension->get_alias()), $container);
        return $configuration->get_config_tree_builder()->get_root_node()->get_node(true)->get_attribute('docUrl');
    }
}