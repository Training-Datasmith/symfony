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
namespace Symfony\Component\Form\Command;

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
use Symfony\Component\Error_Handler\Error_Renderer\File_Link_Formatter;
use Symfony\Component\Form\Console\Helper\Descriptor_Helper;
use Symfony\Component\Form\Extension\Core\Core_Extension;
use Symfony\Component\Form\Form_Registry_Interface;
use Symfony\Component\Form\Form_Type_Interface;
/**
 * A console command for retrieving information about form types.
 *
 * @author Yonel Ceruto <yonelceruto@gmail.com>
 */
#[As_Command(name: 'debug:form', description: 'Display form type information')]
class Debug_Command extends Command
{
    public function __construct(private readonly Form_Registry_Interface $form_registry, private array $namespaces = ['Symfony\Component\Form\Extension\Core\Type'], private readonly array $types = [], private readonly array $extensions = [], private readonly array $guessers = [], private readonly ?File_Link_Formatter $file_link_formatter = null)
    {
        parent::__construct();
    }
    protected function configure(): void
    {
        $this->set_definition([new Input_Argument('class', Input_Argument::OPTIONAL, 'The form type class'), new Input_Argument('option', Input_Argument::OPTIONAL, 'The form type option'), new Input_Option('show-deprecated', null, Input_Option::VALUE_NONE, 'Display deprecated options in form types'), new Input_Option('format', null, Input_Option::VALUE_REQUIRED, \sprintf('The output format ("%s")', implode('", "', $this->get_available_format_options())), 'txt')])->set_help(<<<'EOF'
        The <info>%command.name%</info> command displays information about form types.
        
          <info>php %command.full_name%</info>
        
        The command lists all built-in types, services types, type extensions and
        guessers currently available.
        
          <info>php %command.full_name% Symfony\Component\Form\Extension\Core\Type\ChoiceType</info>
          <info>php %command.full_name% ChoiceType</info>
        
        The command lists all defined options that contains the given form type,
        as well as their parents and type extensions.
        
          <info>php %command.full_name% ChoiceType choice_value</info>
        
        Use the <info>--show-deprecated</info> option to display form types with
        deprecated options or the deprecated options of the given form type:
        
          <info>php %command.full_name% --show-deprecated</info>
          <info>php %command.full_name% ChoiceType --show-deprecated</info>
        
        The command displays the definition of the given option name.
        
          <info>php %command.full_name% --format=json</info>
        
        The command lists everything in a machine readable json format.
        EOF);
    }
    protected function execute(Input_Interface $input, Output_Interface $output): int
    {
        $io = new Symfony_Style($input, $output);
        if (null === $class = $input->get_argument('class')) {
            $object = null;
            $options['core_types'] = $this->get_core_types();
            $options['service_types'] = array_values(array_diff($this->types, $options['core_types']));
            if ($input->get_option('show-deprecated')) {
                $options['core_types'] = $this->filter_types_by_deprecated($options['core_types']);
                $options['service_types'] = $this->filter_types_by_deprecated($options['service_types']);
            }
            $options['extensions'] = $this->extensions;
            $options['guessers'] = $this->guessers;
            foreach ($options as $k => $list) {
                sort($options[$k]);
            }
        } else {
            if (!class_exists($class) || !is_subclass_of($class, Form_Type_Interface::class)) {
                $class = $this->get_fqcn_type_class($input, $io, $class);
            }
            $resolved_type = $this->form_registry->get_type($class);
            if ($option = $input->get_argument('option')) {
                $object = $resolved_type->get_options_resolver();
                if (!$object->is_defined($option)) {
                    $message = \sprintf('Option "%s" is not defined in "%s".', $option, $resolved_type->get_inner_type()::class);
                    if ($alternatives = $this->find_alternatives($option, $object->get_defined_options())) {
                        if (1 === \count($alternatives)) {
                            $message .= "\n\nDid you mean this?\n    ";
                        } else {
                            $message .= "\n\nDid you mean one of these?\n    ";
                        }
                        $message .= implode("\n    ", $alternatives);
                    }
                    throw new InvalidArgumentException($message);
                }
                $options['type'] = $resolved_type->get_inner_type();
                $options['option'] = $option;
            } else {
                $object = $resolved_type;
            }
        }
        $helper = new Descriptor_Helper($this->file_link_formatter);
        $options['format'] = $input->get_option('format');
        $options['show_deprecated'] = $input->get_option('show-deprecated');
        $helper->describe($io, $object, $options);
        return 0;
    }
    private function get_fqcn_type_class(Input_Interface $input, Symfony_Style $io, string $short_class_name): string
    {
        $classes = $this->get_fqcn_type_classes($short_class_name);
        if (0 === $count = \count($classes)) {
            $message = \sprintf("Could not find type \"%s\" into the following namespaces:\n    %s", $short_class_name, implode("\n    ", $this->namespaces));
            $all_types = array_merge($this->get_core_types(), $this->types);
            if ($alternatives = $this->find_alternatives($short_class_name, $all_types)) {
                if (1 === \count($alternatives)) {
                    $message .= "\n\nDid you mean this?\n    ";
                } else {
                    $message .= "\n\nDid you mean one of these?\n    ";
                }
                $message .= implode("\n    ", $alternatives);
            }
            throw new InvalidArgumentException($message);
        }
        if (1 === $count) {
            return $classes[0];
        }
        if (!$input->is_interactive()) {
            throw new InvalidArgumentException(\sprintf("The type \"%s\" is ambiguous.\n\nDid you mean one of these?\n    %s.", $short_class_name, implode("\n    ", $classes)));
        }
        return $io->choice(\sprintf("The type \"%s\" is ambiguous.\n\nSelect one of the following form types to display its information:", $short_class_name), $classes, $classes[0]);
    }
    private function get_fqcn_type_classes(string $short_class_name): array
    {
        $classes = [];
        sort($this->namespaces);
        foreach ($this->namespaces as $namespace) {
            if (class_exists($fqcn = $namespace . '\\' . $short_class_name)) {
                $classes[] = $fqcn;
            } elseif (class_exists($fqcn = $namespace . '\\' . ucfirst($short_class_name))) {
                $classes[] = $fqcn;
            } elseif (class_exists($fqcn = $namespace . '\\' . ucfirst($short_class_name) . 'Type')) {
                $classes[] = $fqcn;
            } elseif (str_ends_with($short_class_name, 'type') && class_exists($fqcn = $namespace . '\\' . ucfirst(substr($short_class_name, 0, -4) . 'Type'))) {
                $classes[] = $fqcn;
            }
        }
        return $classes;
    }
    private function get_core_types(): array
    {
        $core_extension = new Core_Extension();
        $load_types_ref_method = (new \Reflection_Object($core_extension))->get_method('loadTypes');
        $core_types = $load_types_ref_method->invoke($core_extension);
        $core_types = array_map(static fn(Form_Type_Interface $type): string => $type::class, $core_types);
        sort($core_types);
        return $core_types;
    }
    private function filter_types_by_deprecated(array $types): array
    {
        $types_with_deprecated_options = [];
        foreach ($types as $class) {
            $options_resolver = $this->form_registry->get_type($class)->get_options_resolver();
            foreach ($options_resolver->get_defined_options() as $option) {
                if ($options_resolver->is_deprecated($option)) {
                    $types_with_deprecated_options[] = $class;
                    break;
                }
            }
        }
        return $types_with_deprecated_options;
    }
    private function find_alternatives(string $name, array $collection): array
    {
        $alternatives = [];
        foreach ($collection as $item) {
            $lev = levenshtein($name, $item);
            if ($lev <= \strlen($name) / 3 || str_contains((string) $item, $name)) {
                $alternatives[$item] = isset($alternatives[$item]) ? $alternatives[$item] - $lev : $lev;
            }
        }
        $threshold = 1000.0;
        $alternatives = array_filter($alternatives, static fn(int $lev): bool => $lev < 2 * $threshold);
        ksort($alternatives, \SORT_NATURAL | \SORT_FLAG_CASE);
        return array_keys($alternatives);
    }
    public function complete(Completion_Input $input, Completion_Suggestions $suggestions): void
    {
        if ($input->must_suggest_argument_values_for('class')) {
            $suggestions->suggest_values(array_merge($this->get_core_types(), $this->types));
            return;
        }
        if ($input->must_suggest_argument_values_for('option') && null !== $class = $input->get_argument('class')) {
            $this->complete_options($class, $suggestions);
            return;
        }
        if ($input->must_suggest_option_values_for('format')) {
            $suggestions->suggest_values($this->get_available_format_options());
        }
    }
    private function complete_options(string $class, Completion_Suggestions $suggestions): void
    {
        if (!class_exists($class) || !is_subclass_of($class, Form_Type_Interface::class)) {
            $classes = $this->get_fqcn_type_classes($class);
            if (1 === \count($classes)) {
                $class = $classes[0];
            }
        }
        if (!$this->form_registry->has_type($class)) {
            return;
        }
        $resolved_type = $this->form_registry->get_type($class);
        $suggestions->suggest_values($resolved_type->get_options_resolver()->get_defined_options());
    }
    /** @return string[] */
    private function get_available_format_options(): array
    {
        return (new Descriptor_Helper())->get_formats();
    }
}