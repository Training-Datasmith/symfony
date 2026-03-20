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
use Symfony\Component\Config\Definition\Configuration_Interface;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\Console\Attribute\As_Command;
use Symfony\Component\Console\Completion\Completion_Input;
use Symfony\Component\Console\Completion\Completion_Suggestions;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Exception\LogicException;
use Symfony\Component\Console\Input\Input_Argument;
use Symfony\Component\Console\Input\Input_Interface;
use Symfony\Component\Console\Input\Input_Option;
use Symfony\Component\Console\Output\Output_Interface;
use Symfony\Component\Console\Style\Symfony_Style;
use Symfony\Component\Dependency_Injection\Compiler\Validate_Env_Placeholders_Pass;
use Symfony\Component\Dependency_Injection\Container_Builder;
use Symfony\Component\Dependency_Injection\Extension\Configuration_Extension_Interface;
use Symfony\Component\Dependency_Injection\Extension\Extension_Interface;
use Symfony\Component\Yaml\Yaml;
/**
 * A console command for dumping available configuration reference.
 *
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 *
 * @final
 */
#[As_Command(name: 'debug:config', description: 'Dump the current configuration for an extension')]
class Config_Debug_Command extends Abstract_Config_Command
{
    public function __construct(private readonly ?Container_Interface $env_var_processors = null)
    {
        parent::__construct();
    }
    protected function configure(): void
    {
        $this->set_definition([new Input_Argument('name', Input_Argument::OPTIONAL, 'The bundle name or the extension alias'), new Input_Argument('path', Input_Argument::OPTIONAL, 'The configuration option path'), new Input_Option('resolve-env', null, Input_Option::VALUE_NONE, 'Display resolved environment variable values instead of placeholders'), new Input_Option('format', null, Input_Option::VALUE_REQUIRED, \sprintf('The output format ("%s")', implode('", "', $this->get_available_format_options())), class_exists(Yaml::class) ? 'txt' : 'json')])->set_help(<<<EOF
        The <info>%command.name%</info> command dumps the current configuration for an
        extension/bundle.
        
        Either the extension alias or bundle name can be used:
        
          <info>php %command.full_name% framework</info>
          <info>php %command.full_name% FrameworkBundle</info>
        
        The <info>--format</info> option specifies the format of the command output:
        
          <info>php %command.full_name% framework --format=json</info>
        
        For dumping a specific option, add its path as second argument:
        
          <info>php %command.full_name% framework serializer.enabled</info>
        
        EOF);
    }
    protected function execute(Input_Interface $input, Output_Interface $output): int
    {
        $io = new Symfony_Style($input, $output);
        $error_io = $io->get_error_style();
        if (null === $name = $input->get_argument('name')) {
            $this->list_bundles($error_io);
            $this->list_non_bundle_extensions($error_io);
            $error_io->comment('Provide the name of a bundle as the first argument of this command to dump its configuration. (e.g. <comment>debug:config FrameworkBundle</comment>)');
            $error_io->comment('For dumping a specific option, add its path as the second argument of this command. (e.g. <comment>debug:config FrameworkBundle serializer</comment> to dump the <comment>framework.serializer</comment> configuration)');
            return 0;
        }
        $extension = $this->find_extension($name);
        $extension_alias = $extension->get_alias();
        $container = $this->compile_container();
        $config = $this->get_config($extension, $container, $input->get_option('resolve-env'));
        $format = $input->get_option('format');
        if (\in_array($format, ['txt', 'yml'], true) && !class_exists(Yaml::class)) {
            $error_io->error('Setting the "format" option to "txt" or "yaml" requires the Symfony Yaml component. Try running "composer install symfony/yaml" or use "--format=json" instead.');
            return 1;
        }
        if (null === $path = $input->get_argument('path')) {
            if ('txt' === $input->get_option('format')) {
                $io->title(\sprintf('Current configuration for %s', $name === $extension_alias ? \sprintf('extension with alias "%s"', $extension_alias) : \sprintf('"%s"', $name)));
                if ($doc_url = $this->get_doc_url($extension, $container)) {
                    $io->comment(\sprintf('Documentation at %s', $doc_url));
                }
            }
            $io->writeln($this->convert_to_format([$extension_alias => $config], $format));
            return 0;
        }
        try {
            $config = $this->get_config_for_path($config, $path, $extension_alias);
        } catch (LogicException $e) {
            $error_io->error($e->get_message());
            return 1;
        }
        $io->title(\sprintf('Current configuration for "%s.%s"', $extension_alias, $path));
        $io->writeln($this->convert_to_format($config, $format));
        return 0;
    }
    private function convert_to_format(mixed $config, string $format): string
    {
        return match ($format) {
            'txt', 'yaml' => Yaml::dump($config, 10),
            'json' => json_encode($config, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE),
            default => throw new InvalidArgumentException(\sprintf('Supported formats are "%s".', implode('", "', $this->get_available_format_options()))),
        };
    }
    private function compile_container(): Container_Builder
    {
        $kernel = clone $this->get_application()->get_kernel();
        $kernel->boot();
        $method = new \ReflectionMethod($kernel, 'buildContainer');
        $container = $method->invoke($kernel);
        if ($this->env_var_processors) {
            $container->set('container.env_var_processors_locator', $this->env_var_processors);
        }
        $container->get_compiler()->compile($container);
        return $container;
    }
    /**
     * Iterate over configuration until the last step of the given path.
     *
     * @throws LogicException If the configuration does not exist
     */
    private function get_config_for_path(array $config, string $path, string $alias): mixed
    {
        $steps = explode('.', $path);
        foreach ($steps as $step) {
            if (!\is_array($config) || !\array_key_exists($step, $config)) {
                throw new LogicException(\sprintf('Unable to find configuration for "%s.%s".', $alias, $path));
            }
            $config = $config[$step];
        }
        return $config;
    }
    private function get_config_for_extension(Extension_Interface $extension, Container_Builder $container): array
    {
        $extension_alias = $extension->get_alias();
        $extension_config = [];
        foreach ($container->get_compiler_pass_config()->get_passes() as $pass) {
            if ($pass instanceof Validate_Env_Placeholders_Pass) {
                $extension_config = $pass->get_extension_config();
                break;
            }
        }
        if (isset($extension_config[$extension_alias])) {
            return $extension_config[$extension_alias];
        }
        // Fall back to default config if the extension has one
        if (!$extension instanceof Configuration_Extension_Interface && !$extension instanceof Configuration_Interface) {
            throw new \LogicException(\sprintf('The extension with alias "%s" does not have configuration.', $extension_alias));
        }
        $configs = $container->get_extension_config($extension_alias);
        $configuration = $extension instanceof Configuration_Interface ? $extension : $extension->get_configuration($configs, $container);
        $this->validate_configuration($extension, $configuration);
        return (new Processor())->process_configuration($configuration, $configs);
    }
    public function complete(Completion_Input $input, Completion_Suggestions $suggestions): void
    {
        if ($input->must_suggest_argument_values_for('name')) {
            $suggestions->suggest_values($this->get_available_extensions());
            $suggestions->suggest_values($this->get_available_bundles());
            return;
        }
        if ($input->must_suggest_argument_values_for('path') && null !== $name = $input->get_argument('name')) {
            try {
                $config = $this->get_config($this->find_extension($name), $this->compile_container());
                $paths = array_keys(self::build_paths_completion($config));
                $suggestions->suggest_values($paths);
            } catch (LogicException) {
            }
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
        $available_bundles = [];
        foreach ($this->get_application()->get_kernel()->get_bundles() as $bundle) {
            $available_bundles[] = $bundle->get_name();
        }
        return $available_bundles;
    }
    private function get_config(Extension_Interface $extension, Container_Builder $container, bool $resolve_envs = false): mixed
    {
        return $container->resolve_env_placeholders($container->get_parameter_bag()->resolve_value($this->get_config_for_extension($extension, $container)), $resolve_envs ?: null);
    }
    private static function build_paths_completion(array $paths, string $prefix = ''): array
    {
        $completion_paths = [];
        foreach ($paths as $key => $values) {
            if (\is_array($values)) {
                $completion_paths += self::build_paths_completion($values, $prefix . $key . '.');
            } else {
                $completion_paths[$prefix . $key] = null;
            }
        }
        return $completion_paths;
    }
    /** @return string[] */
    private function get_available_format_options(): array
    {
        return ['txt', 'yaml', 'json'];
    }
    private function get_doc_url(Extension_Interface $extension, Container_Builder $container): ?string
    {
        $configuration = $extension instanceof Configuration_Interface ? $extension : $extension->get_configuration($container->get_extension_config($extension->get_alias()), $container);
        return $configuration->get_config_tree_builder()->get_root_node()->get_node(true)->get_attribute('docUrl');
    }
}