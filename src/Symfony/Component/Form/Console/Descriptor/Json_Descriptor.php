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

use Symfony\Component\Form\Resolved_Form_Type_Interface;
use Symfony\Component\Options_Resolver\Options_Resolver;
/**
 * @author Yonel Ceruto <yonelceruto@gmail.com>
 *
 * @internal
 */
class Json_Descriptor extends Descriptor
{
    protected function describe_defaults(array $options): void
    {
        $data['builtin_form_types'] = $options['core_types'];
        $data['service_form_types'] = $options['service_types'];
        if (!$options['show_deprecated']) {
            $data['type_extensions'] = $options['extensions'];
            $data['type_guessers'] = $options['guessers'];
        }
        $this->write_data($data, $options);
    }
    protected function describe_resolved_form_type(Resolved_Form_Type_Interface $resolved_form_type, array $options = []): void
    {
        $this->collect_options($resolved_form_type);
        if ($options['show_deprecated']) {
            $this->filter_options_by_deprecated($resolved_form_type);
        }
        $form_options = ['own' => $this->own_options, 'overridden' => $this->overridden_options, 'parent' => $this->parent_options, 'extension' => $this->extension_options, 'required' => $this->required_options];
        $this->sort_options($form_options);
        $data = ['class' => $resolved_form_type->get_inner_type()::class, 'block_prefix' => $resolved_form_type->get_inner_type()->get_block_prefix(), 'options' => $form_options, 'parent_types' => $this->parents, 'type_extensions' => $this->extensions];
        $this->write_data($data, $options);
    }
    protected function describe_option(Options_Resolver $options_resolver, array $options): void
    {
        $data = $this->get_option_description($options_resolver, $options);
        $this->write_data($data, $options);
    }
    private function get_option_description(Options_Resolver $options_resolver, array $options): array
    {
        $definition = $this->get_option_definition($options_resolver, $options['option']);
        $map = [];
        if ($definition['deprecated']) {
            $map['deprecated'] = 'deprecated';
            if (\is_string($definition['deprecationMessage'])) {
                $map['deprecation_message'] = 'deprecationMessage';
            }
        }
        $map += ['info' => 'info', 'required' => 'required', 'default' => 'default', 'allowed_types' => 'allowedTypes', 'allowed_values' => 'allowedValues'];
        foreach ($map as $label => $name) {
            if (\array_key_exists($name, $definition)) {
                $data[$label] = $definition[$name];
                if ('default' === $name) {
                    $data['is_lazy'] = isset($definition['lazy']);
                }
            }
        }
        $data['has_normalizer'] = isset($definition['normalizers']);
        if ($data['has_nested_options'] = isset($definition['nestedOptions'])) {
            $nested_resolver = new Options_Resolver();
            foreach ($definition['nestedOptions'] as $nested_option) {
                $nested_option($nested_resolver, $options_resolver);
            }
            foreach ($nested_resolver->get_defined_options() as $option) {
                $data['nested_options'][$option] = $this->get_option_description($nested_resolver, ['option' => $option]);
            }
        }
        return $data;
    }
    private function write_data(array $data, array $options): void
    {
        $flags = $options['json_encoding'] ?? 0;
        $this->output->write(json_encode($data, $flags | \JSON_PRETTY_PRINT) . "\n");
    }
    private function sort_options(array &$options): void
    {
        foreach ($options as &$opts) {
            $sorted = false;
            foreach ($opts as &$opt) {
                if (\is_array($opt)) {
                    sort($opt);
                    $sorted = true;
                }
            }
            if (!$sorted) {
                sort($opts);
            }
        }
    }
}