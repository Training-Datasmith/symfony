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
namespace Symfony\Component\Form\Console\Descriptor;

use Symfony\Component\Console\Helper\Dumper;
use Symfony\Component\Console\Helper\Table_Separator;
use Symfony\Component\Error_Handler\Error_Renderer\File_Link_Formatter;
use Symfony\Component\Form\Resolved_Form_Type_Interface;
use Symfony\Component\Options_Resolver\Options_Resolver;
/**
 * @author Yonel Ceruto <yonelceruto@gmail.com>
 *
 * @internal
 */
class Text_Descriptor extends Descriptor
{
    public function __construct(private readonly ?File_Link_Formatter $file_link_formatter = null)
    {
    }
    protected function describe_defaults(array $options): void
    {
        if ($options['core_types']) {
            $this->output->section('Built-in form types (Symfony\Component\Form\Extension\Core\Type)');
            $short_class_names = array_map(fn(string $fqcn): string => $this->format_class_link($fqcn, \array_slice(explode('\\', $fqcn), -1)[0]), $options['core_types']);
            for ($i = 0, $loops_max = \count($short_class_names); $i * 5 < $loops_max; ++$i) {
                $this->output->writeln(' ' . implode(', ', \array_slice($short_class_names, $i * 5, 5)));
            }
        }
        if ($options['service_types']) {
            $this->output->section('Service form types');
            $this->output->listing(array_map($this->format_class_link(...), $options['service_types']));
        }
        if (!$options['show_deprecated']) {
            if ($options['extensions']) {
                $this->output->section('Type extensions');
                $this->output->listing(array_map($this->format_class_link(...), $options['extensions']));
            }
            if ($options['guessers']) {
                $this->output->section('Type guessers');
                $this->output->listing(array_map($this->format_class_link(...), $options['guessers']));
            }
        }
    }
    protected function describe_resolved_form_type(Resolved_Form_Type_Interface $resolved_form_type, array $options = []): void
    {
        $this->collect_options($resolved_form_type);
        if ($options['show_deprecated']) {
            $this->filter_options_by_deprecated($resolved_form_type);
        }
        $form_options = $this->normalize_and_sort_options_columns(array_filter(['own' => $this->own_options, 'overridden' => $this->overridden_options, 'parent' => $this->parent_options, 'extension' => $this->extension_options]));
        // setting headers and column order
        $table_headers = array_intersect_key(['own' => 'Options', 'overridden' => 'Overridden options', 'parent' => 'Parent options', 'extension' => 'Extension options'], $form_options);
        $this->output->title(\sprintf('%s (Block prefix: "%s")', $resolved_form_type->get_inner_type()::class, $resolved_form_type->get_inner_type()->get_block_prefix()));
        if ($form_options) {
            $this->output->table($table_headers, $this->build_table_rows($table_headers, $form_options));
        }
        if ($this->parents) {
            $this->output->section('Parent types');
            $this->output->listing(array_map($this->format_class_link(...), $this->parents));
        }
        if ($this->extensions) {
            $this->output->section('Type extensions');
            $this->output->listing(array_map($this->format_class_link(...), $this->extensions));
        }
    }
    protected function describe_option(Options_Resolver $options_resolver, array $options): void
    {
        $definition = $this->get_option_definition($options_resolver, $options['option']);
        $dump = new Dumper($this->output);
        $map = [];
        if ($definition['deprecated']) {
            $map = ['Deprecated' => 'deprecated', 'Deprecation package' => 'deprecationPackage', 'Deprecation version' => 'deprecationVersion', 'Deprecation message' => 'deprecationMessage'];
        }
        $map += ['Info' => 'info', 'Required' => 'required', 'Default' => 'default', 'Allowed types' => 'allowedTypes', 'Allowed values' => 'allowedValues', 'Normalizers' => 'normalizers', 'Nested Options' => 'nestedOptions'];
        $rows = [];
        foreach ($map as $label => $name) {
            $value = \array_key_exists($name, $definition) ? $dump($definition[$name]) : '-';
            if ('default' === $name && isset($definition['lazy'])) {
                $value = "Value: {$value}\n\nClosure(s): " . $dump($definition['lazy']);
            } elseif ('nestedOptions' === $name && isset($definition['nestedOptions'])) {
                $nested_resolver = new Options_Resolver();
                foreach ($definition['nestedOptions'] as $nested_option) {
                    $nested_option($nested_resolver, $options_resolver);
                }
                $value = $dump($nested_resolver->get_defined_options());
            }
            $rows[] = ["<info>{$label}</info>", $value];
            $rows[] = new Table_Separator();
        }
        array_pop($rows);
        $this->output->title(\sprintf('%s (%s)', $options['type']::class, $options['option']));
        $this->output->table([], $rows);
    }
    private function build_table_rows(array $headers, array $options): array
    {
        $table_rows = [];
        $count = \count(max($options));
        for ($i = 0; $i < $count; ++$i) {
            $cells = [];
            foreach (array_keys($headers) as $group) {
                $option = $options[$group][$i] ?? null;
                if (\is_string($option) && \in_array($option, $this->required_options, true)) {
                    $option .= ' <info>(required)</info>';
                }
                $cells[] = $option;
            }
            $table_rows[] = $cells;
        }
        return $table_rows;
    }
    private function normalize_and_sort_options_columns(array $options): array
    {
        foreach ($options as $group => $opts) {
            $sorted = false;
            foreach ($opts as $class => $opt) {
                if (\is_string($class)) {
                    unset($options[$group][$class]);
                }
                if (!\is_array($opt)) {
                    continue;
                }
                if (0 === \count($opt)) {
                    continue;
                }
                if (!$sorted) {
                    $options[$group] = [];
                } else {
                    $options[$group][] = null;
                }
                $options[$group][] = \sprintf('<info>%s</info>', (new \ReflectionClass($class))->get_short_name());
                $options[$group][] = new Table_Separator();
                sort($opt);
                $sorted = true;
                $options[$group] = array_merge($options[$group], $opt);
            }
            if (!$sorted) {
                sort($options[$group]);
            }
        }
        return $options;
    }
    private function format_class_link(string $class, ?string $text = null): string
    {
        $text ??= $class;
        if ('' === $file_link = $this->get_file_link($class)) {
            return $text;
        }
        return \sprintf('<href=%s>%s</>', $file_link, $text);
    }
    private function get_file_link(string $class): string
    {
        if (null === $this->file_link_formatter) {
            return '';
        }
        try {
            $r = new \ReflectionClass($class);
        } catch (\Reflection_Exception) {
            return '';
        }
        return (string) $this->file_link_formatter->format($r->get_file_name(), $r->get_start_line());
    }
}