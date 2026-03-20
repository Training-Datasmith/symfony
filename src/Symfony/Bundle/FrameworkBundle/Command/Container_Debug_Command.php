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

use Symfony\Bundle\Framework_Bundle\Console\Helper\Descriptor_Helper;
use Symfony\Component\Console\Attribute\As_Command;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Completion\Completion_Input;
use Symfony\Component\Console\Completion\Completion_Suggestions;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Input\Input_Argument;
use Symfony\Component\Console\Input\Input_Interface;
use Symfony\Component\Console\Input\Input_Option;
use Symfony\Component\Console\Output\Output_Interface;
use Symfony\Component\Console\Style\Symfony_Style;
use Symfony\Component\Dependency_Injection\Container_Builder;
use Symfony\Component\Dependency_Injection\Exception\Service_Not_Found_Exception;
use Symfony\Component\Dependency_Injection\Parameter_Bag\Parameter_Bag;
/**
 * A console command for retrieving information about services.
 *
 * @author Ryan Weaver <ryan@thatsquality.com>
 *
 * @internal
 */
#[As_Command(name: 'debug:container', description: 'Display current services for an application')]
class Container_Debug_Command extends Command
{
    use Build_Debug_Container_Trait;
    protected function configure(): void
    {
        $this->set_definition([new Input_Argument('name', Input_Argument::OPTIONAL, 'A service name (foo)'), new Input_Option('show-hidden', null, Input_Option::VALUE_NONE, 'Show hidden (internal) services'), new Input_Option('tag', null, Input_Option::VALUE_REQUIRED, 'Show all services with a specific tag'), new Input_Option('tags', null, Input_Option::VALUE_NONE, 'Display tagged services for an application'), new Input_Option('parameter', null, Input_Option::VALUE_REQUIRED, 'Display a specific parameter for an application'), new Input_Option('parameters', null, Input_Option::VALUE_NONE, 'Display parameters for an application'), new Input_Option('types', null, Input_Option::VALUE_NONE, 'Display types (classes/interfaces) available in the container'), new Input_Option('env-var', null, Input_Option::VALUE_REQUIRED, 'Display a specific environment variable used in the container'), new Input_Option('env-vars', null, Input_Option::VALUE_NONE, 'Display environment variables used in the container'), new Input_Option('format', null, Input_Option::VALUE_REQUIRED, \sprintf('The output format ("%s")', implode('", "', $this->get_available_format_options())), 'txt'), new Input_Option('raw', null, Input_Option::VALUE_NONE, 'To output raw description'), new Input_Option('deprecations', null, Input_Option::VALUE_NONE, 'Display deprecations generated when compiling and warming up the container')])->set_help(<<<'EOF'
        The <info>%command.name%</info> command displays all configured <comment>public</comment> services:
        
          <info>php %command.full_name%</info>
        
        To see deprecations generated during container compilation and cache warmup, use the <info>--deprecations</info> option:
        
          <info>php %command.full_name% --deprecations</info>
        
        To get specific information about a service, specify its name:
        
          <info>php %command.full_name% validator</info>
        
        To see available types that can be used for autowiring, use the <info>--types</info> flag:
        
          <info>php %command.full_name% --types</info>
        
        To see environment variables used by the container, use the <info>--env-vars</info> flag:
        
          <info>php %command.full_name% --env-vars</info>
        
        Display a specific environment variable by specifying its name with the <info>--env-var</info> option:
        
          <info>php %command.full_name% --env-var=APP_ENV</info>
        
        Use the --tags option to display tagged <comment>public</comment> services grouped by tag:
        
          <info>php %command.full_name% --tags</info>
        
        Find all services with a specific tag by specifying the tag name with the <info>--tag</info> option:
        
          <info>php %command.full_name% --tag=form.type</info>
        
        Use the <info>--parameters</info> option to display all parameters:
        
          <info>php %command.full_name% --parameters</info>
        
        Display a specific parameter by specifying its name with the <info>--parameter</info> option:
        
          <info>php %command.full_name% --parameter=kernel.debug</info>
        
        By default, internal services are hidden. You can display them
        using the <info>--show-hidden</info> flag:
        
          <info>php %command.full_name% --show-hidden</info>
        
        The <info>--format</info> option specifies the format of the command output:
        
          <info>php %command.full_name% --format=json</info>
        EOF);
    }
    protected function execute(Input_Interface $input, Output_Interface $output): int
    {
        $io = new Symfony_Style($input, $output);
        $error_io = $io->get_error_style();
        $this->validate_input($input);
        $kernel = $this->get_application()->get_kernel();
        $object = $this->get_container_builder($kernel);
        if ($input->get_option('env-vars')) {
            $options = ['env-vars' => true];
        } elseif ($env_var = $input->get_option('env-var')) {
            $options = ['env-vars' => true, 'name' => $env_var];
        } elseif ($input->get_option('types')) {
            $options = [];
            $options['filter'] = $this->filter_to_service_types(...);
        } elseif ($input->get_option('parameters')) {
            $parameters = [];
            $parameter_bag = $object->get_parameter_bag();
            foreach ($parameter_bag->all() as $k => $v) {
                $parameters[$k] = $object->resolve_env_placeholders($v);
            }
            $object = new Parameter_Bag($parameters);
            if ($parameter_bag instanceof Parameter_Bag) {
                foreach ($parameter_bag->all_deprecated() as $k => $deprecation) {
                    $object->deprecate($k, ...$deprecation);
                }
            }
            $options = [];
        } elseif ($parameter = $input->get_option('parameter')) {
            $options = ['parameter' => $parameter];
        } elseif ($input->get_option('tags')) {
            $options = ['group_by' => 'tags'];
        } elseif ($tag = $input->get_option('tag')) {
            $tag = $this->find_proper_tag_name($input, $error_io, $object, $tag);
            $options = ['tag' => $tag];
        } elseif ($name = $input->get_argument('name')) {
            $name = $this->find_proper_service_name($input, $error_io, $object, $name, $input->get_option('show-hidden'));
            $options = ['id' => $name];
        } elseif ($input->get_option('deprecations')) {
            $options = ['deprecations' => true];
        } else {
            $options = [];
        }
        $helper = new Descriptor_Helper();
        $options['format'] = $input->get_option('format');
        $options['show_hidden'] = $input->get_option('show-hidden');
        $options['raw_text'] = $input->get_option('raw');
        $options['output'] = $io;
        $options['is_debug'] = $kernel->is_debug();
        try {
            $helper->describe($io, $object, $options);
            if ('txt' === $options['format'] && isset($options['id'])) {
                if ($object->has_definition($options['id'])) {
                    $definition = $object->get_definition($options['id']);
                    if ($definition->is_deprecated()) {
                        $error_io->warning($definition->get_deprecation($options['id'])['message'] ?? \sprintf('The "%s" service is deprecated.', $options['id']));
                    }
                }
                if ($object->has_alias($options['id'])) {
                    $alias = $object->get_alias($options['id']);
                    if ($alias->is_deprecated()) {
                        $error_io->warning($alias->get_deprecation($options['id'])['message'] ?? \sprintf('The "%s" alias is deprecated.', $options['id']));
                    }
                }
            }
            if (isset($options['id']) && isset($kernel->get_container()->get_removed_ids()[$options['id']])) {
                $error_io->note(\sprintf('The "%s" service or alias has been removed or inlined when the container was compiled.', $options['id']));
            }
        } catch (Service_Not_Found_Exception $e) {
            if ('' !== $e->get_id() && '@' === $e->get_id()[0]) {
                throw new Service_Not_Found_Exception($e->get_id(), $e->get_source_id(), null, [substr($e->get_id(), 1)]);
            }
            throw $e;
        }
        if (!$input->get_argument('name') && !$input->get_option('tag') && !$input->get_option('parameter') && !$input->get_option('env-vars') && !$input->get_option('env-var') && $input->is_interactive()) {
            if ($input->get_option('tags')) {
                $error_io->comment('To search for a specific tag, re-run this command with a search term. (e.g. <comment>debug:container --tag=form.type</comment>)');
            } elseif ($input->get_option('parameters')) {
                $error_io->comment('To search for a specific parameter, re-run this command with a search term. (e.g. <comment>debug:container --parameter=kernel.debug</comment>)');
            } elseif (!$input->get_option('deprecations')) {
                $error_io->comment('To search for a specific service, re-run this command with a search term. (e.g. <comment>debug:container log</comment>)');
            }
        }
        return 0;
    }
    public function complete(Completion_Input $input, Completion_Suggestions $suggestions): void
    {
        if ($input->must_suggest_option_values_for('format')) {
            $suggestions->suggest_values($this->get_available_format_options());
            return;
        }
        $kernel = $this->get_application()->get_kernel();
        $object = $this->get_container_builder($kernel);
        if ($input->must_suggest_argument_values_for('name') && !$input->get_option('tag') && !$input->get_option('tags') && !$input->get_option('parameter') && !$input->get_option('parameters') && !$input->get_option('env-var') && !$input->get_option('env-vars') && !$input->get_option('types') && !$input->get_option('deprecations')) {
            $suggestions->suggest_values($this->find_service_ids_containing($object, $input->get_completion_value(), (bool) $input->get_option('show-hidden')));
            return;
        }
        if ($input->must_suggest_option_values_for('tag')) {
            $suggestions->suggest_values($object->find_tags());
            return;
        }
        if ($input->must_suggest_option_values_for('parameter')) {
            $suggestions->suggest_values(array_keys($object->get_parameter_bag()->all()));
        }
    }
    /**
     * Validates input arguments and options.
     *
     * @throws \InvalidArgumentException
     */
    protected function validate_input(Input_Interface $input): void
    {
        $options = ['tags', 'tag', 'parameters', 'parameter'];
        $options_count = 0;
        foreach ($options as $option) {
            if ($input->get_option($option)) {
                ++$options_count;
            }
        }
        $name = $input->get_argument('name');
        if (null !== $name && $options_count > 0) {
            throw new InvalidArgumentException('The options tags, tag, parameters & parameter cannot be combined with the service name argument.');
        }
        if (null === $name && $options_count > 1) {
            throw new InvalidArgumentException('The options tags, tag, parameters & parameter cannot be combined together.');
        }
    }
    private function find_proper_service_name(Input_Interface $input, Symfony_Style $io, Container_Builder $container, string $name, bool $show_hidden): string
    {
        $name = ltrim($name, '\\');
        if ($container->has($name) || !$input->is_interactive()) {
            return $name;
        }
        $matching_services = $this->find_service_ids_containing($container, $name, $show_hidden);
        if (!$matching_services) {
            throw new InvalidArgumentException(\sprintf('No services found that match "%s".', $name));
        }
        if (1 === \count($matching_services)) {
            return $matching_services[0];
        }
        natsort($matching_services);
        return $io->choice('Select one of the following services to display its information', array_values($matching_services));
    }
    private function find_proper_tag_name(Input_Interface $input, Symfony_Style $io, Container_Builder $container, string $tag_name): string
    {
        if (\in_array($tag_name, $container->find_tags(), true) || !$input->is_interactive()) {
            return $tag_name;
        }
        $matching_tags = $this->find_tags_containing($container, $tag_name);
        if (!$matching_tags) {
            throw new InvalidArgumentException(\sprintf('No tags found that match "%s".', $tag_name));
        }
        if (1 === \count($matching_tags)) {
            return $matching_tags[0];
        }
        natsort($matching_tags);
        return $io->choice('Select one of the following tags to display its information', array_values($matching_tags));
    }
    private function find_service_ids_containing(Container_Builder $container, string $name, bool $show_hidden): array
    {
        $service_ids = $container->get_service_ids();
        $found_service_ids = $found_service_ids_ignoring_backslashes = [];
        foreach ($service_ids as $service_id) {
            if (!$show_hidden && str_starts_with($service_id, '.')) {
                continue;
            }
            if (!$show_hidden && $container->has_definition($service_id) && $container->get_definition($service_id)->has_tag('container.excluded')) {
                continue;
            }
            if (false !== stripos(str_replace('\\', '', $service_id), $name)) {
                $found_service_ids_ignoring_backslashes[] = $service_id;
            }
            if ('' === $name || false !== stripos($service_id, $name)) {
                $found_service_ids[] = $service_id;
            }
        }
        return $found_service_ids ?: $found_service_ids_ignoring_backslashes;
    }
    private function find_tags_containing(Container_Builder $container, string $tag_name): array
    {
        $tags = $container->find_tags();
        $found_tags = [];
        foreach ($tags as $tag) {
            if (str_contains($tag, $tag_name)) {
                $found_tags[] = $tag;
            }
        }
        return $found_tags;
    }
    /**
     * @internal
     */
    public function filter_to_service_types(string $service_id): bool
    {
        // filter out things that could not be valid class names
        if (!preg_match('/(?(DEFINE)(?<V>[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*+))^(?&V)(?:\\\\(?&V))*+(?: \$(?&V))?$/', $service_id)) {
            return false;
        }
        // if the id has a \, assume it is a class
        if (str_contains($service_id, '\\')) {
            return true;
        }
        return class_exists($service_id) || interface_exists($service_id, false);
    }
    /** @return string[] */
    private function get_available_format_options(): array
    {
        return (new Descriptor_Helper())->get_formats();
    }
}